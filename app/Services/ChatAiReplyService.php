<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Support\CopilotLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ChatAiReplyService
{
    public function __construct(
        private ChatAiContextService $context,
        private ChatConversationService $conversations,
        private CopilotLanguage $language
    ) {
    }

    public function shouldAutoReply(Conversation $conversation): bool
    {
        $mode = strtolower((string) ($conversation->ai_mode ?: 'auto'));

        return $mode === 'auto';
    }

    public function generateForVisitorMessage(
        Conversation $conversation,
        Message $visitorMessage,
        Request $request,
        array $options = []
    ): ?Message {
        if (($options['force'] ?? false) !== true && ! $this->shouldAutoReply($conversation)) {
            return null;
        }

        if (($options['regenerate'] ?? false) !== true) {
            $existing = $this->findExistingReply($conversation, $visitorMessage);
            if ($existing) {
                return $existing->loadMissing(['attachments', 'employee:id,name,email']);
            }
        }

        $resolvedLanguage = $this->language->normalize(
            $options['language']
                ?? $visitorMessage->language
                ?? $conversation->language_preferred
        ) ?? 'en';

        $question = trim((string) ($visitorMessage->text ?: $visitorMessage->body));
        $context = $this->context->build($conversation, $question, $resolvedLanguage);
        $result = $this->generateReplyPayload($conversation, $visitorMessage, $question, $context, $resolvedLanguage, $options);

        // Determine the channel for the AI reply. For WhatsApp conversations the
        // reply must be stored with channel='whatsapp' so that
        // ChatConversationService::addMessage() dispatches SendWhatsAppMessage
        // and the message is actually delivered back to the user.
        $replyChannel = $options['whatsapp_channel']
            ?? ($conversation->channel === 'whatsapp' ? 'whatsapp' : 'web');

        $message = $this->conversations->addMessage($conversation, [
            'sender_type' => 'ai',
            'text' => $result['reply'],
            'language' => $resolvedLanguage,
            'channel' => $replyChannel,
            'message_type' => 'text',
            'status' => 'completed',
            'ai_confidence' => $result['confidence'],
            'delivery_state' => 'sent',
            'metadata' => [
                'provider' => $result['provider'],
                'model' => $result['model'],
                'response_id' => $result['response_id'],
                'in_reply_to_message_id' => $visitorMessage->id,
                'should_handoff' => $result['should_handoff'],
                'handoff_reason' => $result['handoff_reason'],
                'used_sources' => $result['used_sources'],
                'intent_action' => $result['intent_action'] ?? null,
                'intent_payload' => $result['intent_payload'] ?? null,
                'booking_id' => null, // Will be filled below if booking is created
                'context_summary' => [
                    'has_yacht' => $context['yacht'] !== null,
                    'has_location' => $context['location'] !== null,
                    'knowledge_strategy' => data_get($context, 'knowledge.strategy'),
                    'knowledge_confidence' => data_get($context, 'knowledge.confidence'),
                ],
                'knowledge_trace' => data_get($context, 'knowledge.trace'),
                'usage' => $result['usage'],
            ],
            'skip_rate_limit' => true,
            'allow_blocked_contacts' => true,
        ], $request);

        // Execute booking intent if confirmed
        if (($result['intent_action'] ?? null) === 'create_booking' && $context['location']) {
            $payload = $result['intent_payload'] ?? [];
            if (!empty($payload['date']) && !empty($payload['time'])) {
                $booking = \App\Models\Booking::create([
                    'location_id' => $context['location']['id'],
                    'boat_id' => $context['yacht']['id'] ?? null,
                    'type' => 'viewing',
                    'status' => 'confirmed',
                    'date' => $payload['date'],
                    'time' => $payload['time'],
                    'name' => $conversation->contact_name ?? 'Auto Generated',
                    'email' => $conversation->contact_email ?? null,
                    'source' => 'ai_chat',
                ]);
                
                $metadata = $message->metadata ?? [];
                $metadata['booking_id'] = $booking->id;
                $message->metadata = $metadata;
                $message->save();
            }
        }

        if ($result['should_handoff'] && $conversation->status === 'open') {
            $conversation->status = 'pending';
            $conversation->save();
        }

        $this->conversations->recordEvent($conversation, 'chat.ai_reply.created', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'source_message_id' => $visitorMessage->id,
            'provider' => $result['provider'],
            'should_handoff' => $result['should_handoff'],
            'used_sources' => $result['used_sources'],
        ]);

        return $message->loadMissing(['attachments', 'employee:id,name,email']);
    }

    private function findExistingReply(Conversation $conversation, Message $visitorMessage): ?Message
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_type', 'ai')
            ->orderByDesc('created_at')
            ->get()
            ->first(function (Message $message) use ($visitorMessage) {
                return data_get($message->metadata, 'in_reply_to_message_id') === $visitorMessage->id;
            });
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function generateReplyPayload(
        Conversation $conversation,
        Message $visitorMessage,
        string $question,
        array $context,
        string $language,
        array $options
    ): array {
        if ($this->looksLikeGreetingOrSmallTalk($question)) {
            return [
                'reply' => $this->greetingFallback($context, $language),
                'confidence' => 0.44,
                'should_handoff' => false,
                'handoff_reason' => '',
                'used_sources' => $this->contextSources($context),
                'provider' => 'fallback',
                'model' => null,
                'response_id' => null,
                'usage' => null,
            ];
        }

        $exceptions = [];

        foreach ($this->providerSequence() as $provider) {
            try {
                return $this->callProvider(
                    $provider,
                    $conversation,
                    $visitorMessage,
                    $question,
                    $context,
                    $language,
                    $options
                );
            } catch (Throwable $exception) {
                $exceptions[] = $exception;
            }
        }

        if ($exceptions !== []) {
            $lastException = $exceptions[array_key_last($exceptions)];

            return array_merge(
                $this->fallbackReply($question, $context, $language),
                [
                    'provider' => 'fallback',
                    'should_handoff' => true,
                    'handoff_reason' => Str::limit($lastException->getMessage(), 255, ''),
                ]
            );
        }

        return $this->fallbackReply($question, $context, $language);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function callProvider(
        string $provider,
        Conversation $conversation,
        Message $visitorMessage,
        string $question,
        array $context,
        string $language,
        array $options
    ): array {
        return match ($provider) {
            'gemini' => $this->callGemini($conversation, $visitorMessage, $question, $context, $language, $options),
            'openai' => $this->callOpenAi($conversation, $visitorMessage, $question, $context, $language, $options),
            default => throw new \RuntimeException('Unsupported chat AI provider: ' . $provider),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function callOpenAi(
        Conversation $conversation,
        Message $visitorMessage,
        string $question,
        array $context,
        string $language,
        array $options
    ): array {
        $response = Http::withToken((string) config('services.openai.key'))
            ->timeout((int) config('services.openai.chat_timeout', 45))
            ->post('https://api.openai.com/v1/responses', [
                'model' => $this->model(),
                'store' => false,
                'max_output_tokens' => (int) config('services.openai.chat_max_output_tokens', 450),
                'instructions' => $this->instructions($language, $options['instruction'] ?? null),
                'input' => [[
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => json_encode([
                            'task' => 'Answer the customer message using only the supplied system context and knowledge.',
                            'customer_message' => $question,
                            'requested_language' => $language,
                            'system_context' => $context,
                            'reply_constraints' => [
                                'tone' => 'fluent, warm, concise, professional',
                                'max_words' => 140,
                                'no_markdown' => true,
                            ],
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'chat_ai_reply',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
                'metadata' => [
                    'feature' => 'chat_ai_reply',
                    'conversation_id' => $conversation->id,
                    'visitor_message_id' => $visitorMessage->id,
                    'location_id' => (string) $conversation->location_id,
                    'language' => $language,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI chat reply failed: ' . $response->body());
        }

        $body = $response->json();
        $content = $this->extractOutputText((array) ($body['output'] ?? []));
        $parsed = json_decode($content, true);

        if (! is_array($parsed) || ! is_string($parsed['reply'] ?? null)) {
            throw new \RuntimeException('OpenAI chat reply did not return valid structured output.');
        }

        return [
            'reply' => trim((string) $parsed['reply']),
            'confidence' => round(min(1, max(0, (float) ($parsed['confidence'] ?? 0.0))), 2),
            'should_handoff' => (bool) ($parsed['should_handoff'] ?? false),
            'handoff_reason' => Str::limit((string) ($parsed['handoff_reason'] ?? ''), 255, ''),
            'used_sources' => array_values(array_filter(array_map(
                static fn ($value) => is_scalar($value) ? (string) $value : null,
                (array) ($parsed['used_sources'] ?? [])
            ))),
            'intent_action' => $parsed['intent_action'] ?? null,
            'intent_payload' => $parsed['intent_payload'] ?? null,
            'provider' => 'openai',
            'model' => $body['model'] ?? $this->model(),
            'response_id' => $body['id'] ?? null,
            'usage' => $body['usage'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function callGemini(
        Conversation $conversation,
        Message $visitorMessage,
        string $question,
        array $context,
        string $language,
        array $options
    ): array {
        $response = Http::timeout((int) config('services.gemini.chat_timeout', 45))
            ->post(sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                $this->geminiModel(),
                urlencode((string) config('services.gemini.key'))
            ), [
                'system_instruction' => [
                    'parts' => [[
                        'text' => $this->instructions($language, $options['instruction'] ?? null),
                    ]],
                ],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => json_encode([
                            'task' => 'Answer the customer message using only the supplied system context and knowledge.',
                            'customer_message' => $question,
                            'requested_language' => $language,
                            'system_context' => $context,
                            'reply_constraints' => [
                                'tone' => 'fluent, warm, concise, professional',
                                'max_words' => 140,
                                'no_markdown' => true,
                            ],
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => (int) config('services.gemini.chat_max_output_tokens', 450),
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini chat reply failed: ' . $response->body());
        }

        $body = $response->json();
        $content = $this->extractGeminiText($body);
        $parsed = $this->decodeStructuredReply($content, 'Gemini');

        return [
            'reply' => trim((string) $parsed['reply']),
            'confidence' => round(min(1, max(0, (float) ($parsed['confidence'] ?? 0.0))), 2),
            'should_handoff' => (bool) ($parsed['should_handoff'] ?? false),
            'handoff_reason' => Str::limit((string) ($parsed['handoff_reason'] ?? ''), 255, ''),
            'used_sources' => array_values(array_filter(array_map(
                static fn ($value) => is_scalar($value) ? (string) $value : null,
                (array) ($parsed['used_sources'] ?? [])
            ))),
            'provider' => 'gemini',
            'model' => data_get($body, 'modelVersion', $this->geminiModel()),
            'response_id' => data_get($body, 'responseId'),
            'usage' => data_get($body, 'usageMetadata'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function fallbackReply(string $question, array $context, string $language): array
    {
        $knowledgeAnswer = data_get($context, 'knowledge.top_answer.answer');
        $knowledgeConfidence = (float) data_get($context, 'knowledge.confidence', 0.0);

        if (is_string($knowledgeAnswer) && trim($knowledgeAnswer) !== '') {
            return [
                'reply' => trim($knowledgeAnswer),
                'confidence' => round($knowledgeConfidence, 2),
                'should_handoff' => $knowledgeConfidence < 0.68,
                'handoff_reason' => $knowledgeConfidence < 0.68 ? 'low_knowledge_confidence' : '',
                'used_sources' => array_values(array_filter(array_map(
                    static fn ($source) => is_array($source) && ! empty($source['faq_id']) ? 'faq:' . $source['faq_id'] : null,
                    (array) data_get($context, 'knowledge.top_answer.sources', [])
                ))),
                'provider' => 'fallback',
                'model' => null,
                'response_id' => null,
                'usage' => null,
            ];
        }

        if ($this->looksLikeSchedulingRequest($question)) {
            return [
                'reply' => $this->scheduleFallback($language),
                'confidence' => 0.35,
                'should_handoff' => true,
                'handoff_reason' => 'schedule_request_requires_handoff',
                'used_sources' => $this->contextSources($context),
                'provider' => 'fallback',
                'model' => null,
                'response_id' => null,
                'usage' => null,
            ];
        }

        if (is_array($context['yacht'] ?? null)) {
            return [
                'reply' => $this->yachtSummaryFallback((array) $context['yacht'], $language),
                'confidence' => 0.58,
                'should_handoff' => false,
                'handoff_reason' => '',
                'used_sources' => ['yacht:' . data_get($context, 'yacht.id')],
                'provider' => 'fallback',
                'model' => null,
                'response_id' => null,
                'usage' => null,
            ];
        }

        return [
            'reply' => $this->genericFallback($context, $language),
            'confidence' => 0.2,
            'should_handoff' => false,
            'handoff_reason' => '',
            'used_sources' => $this->contextSources($context),
            'provider' => 'fallback',
            'model' => null,
            'response_id' => null,
            'usage' => null,
        ];
    }

    private function instructions(string $language, ?string $instruction = null): string
    {
        $instruction = is_string($instruction) && trim($instruction) !== ''
            ? "\nAdditional operator instruction: " . trim($instruction)
            : '';

        return <<<PROMPT
You are the NauticSecure customer chat assistant.

Rules:
- Answer in {$language}.
- Use only the supplied system context, yacht data, location data, recent conversation history, and knowledge snippets.
- Never invent pricing, availability, specifications, policy terms, appointments, or promises that are not present in the context.
- If context is incomplete, say that briefly and offer a human follow-up.
- If the customer asks to schedule a viewing, asks for a callback, or needs an action a human must perform, try to handle booking if possible, but set should_handoff to true if you are unsure.
- Do not mention internal JSON, internal IDs, or that you are reading system context.
- Keep the reply concise, fluent, and suitable for a customer-facing chat widget.
- If the user explicitly confirms a booking date/time you proposed, set intent_action to "create_booking" and supply the intent_payload with {"date": "YYYY-MM-DD", "time": "HH:MM"}.
- Always confirm with the user before setting intent_action to "create_booking". Use a short confirmation summary first.
- Return valid JSON that matches the schema exactly.
{$instruction}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'reply',
                'confidence',
                'should_handoff',
                'handoff_reason',
                'used_sources',
            ],
            'properties' => [
                'reply' => [
                    'type' => 'string',
                    'description' => 'Customer-facing answer text.',
                ],
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
                'should_handoff' => [
                    'type' => 'boolean',
                ],
                'handoff_reason' => [
                    'type' => 'string',
                    'description' => 'Short machine-readable reason or empty string when no handoff is needed.',
                ],
                'used_sources' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'intent_action' => [
                    'type' => ['string', 'null'],
                    'enum' => ['create_booking', 'check_availability', 'cancel_booking', null],
                    'description' => 'The action the backend should take if recognized.',
                ],
                'intent_payload' => [
                    'type' => ['object', 'null'],
                    'description' => 'A JSON-encoded string representing the payload for the action. e.g. {"date": "2026-03-24", "time": "14:00"}',
                ]
            ],
        ];
    }

    private function extractOutputText(array $output): string
    {
        $parts = [];

        foreach ($output as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractGeminiText(array $body): string
    {
        $parts = [];

        foreach ((array) ($body['candidates'] ?? []) as $candidate) {
            foreach ((array) data_get($candidate, 'content.parts', []) as $part) {
                if (is_string($part['text'] ?? null)) {
                    $parts[] = $part['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeStructuredReply(string $content, string $provider): array
    {
        $cleaned = trim((string) preg_replace('/```json\s*|\s*```/i', '', $content));

        if ($cleaned !== '' && (! str_starts_with($cleaned, '{') || ! str_ends_with($cleaned, '}'))) {
            $start = strpos($cleaned, '{');
            $end = strrpos($cleaned, '}');

            if ($start !== false && $end !== false && $end > $start) {
                $cleaned = substr($cleaned, $start, $end - $start + 1);
            }
        }

        $parsed = json_decode($cleaned, true);

        if (! is_array($parsed) || ! is_string($parsed['reply'] ?? null)) {
            throw new \RuntimeException($provider . ' chat reply did not return valid structured output.');
        }

        return $parsed;
    }

    private function openAiConfigured(): bool
    {
        return (string) config('services.openai.key') !== '';
    }

    private function geminiConfigured(): bool
    {
        return (string) config('services.gemini.key') !== '';
    }

    /**
     * @return array<int, string>
     */
    private function providerSequence(): array
    {
        $configured = array_values(array_filter([
            $this->geminiConfigured() ? 'gemini' : null,
            $this->openAiConfigured() ? 'openai' : null,
        ]));

        $preferred = strtolower(trim((string) config('services.chat_ai.provider', '')));
        if ($preferred !== '' && in_array($preferred, $configured, true)) {
            $others = array_values(array_filter(
                $configured,
                static fn (string $provider) => $provider !== $preferred
            ));

            return array_merge([$preferred], $others);
        }

        if ($this->geminiConfigured()) {
            $others = array_values(array_filter(
                $configured,
                static fn (string $provider) => $provider !== 'gemini'
            ));

            return array_merge(['gemini'], $others);
        }

        return $configured;
    }

    private function model(): string
    {
        return (string) config('services.openai.chat_model', 'gpt-5-mini');
    }

    private function geminiModel(): string
    {
        return (string) config('services.gemini.chat_model', 'gemini-2.5-flash');
    }

    private function looksLikeSchedulingRequest(string $question): bool
    {
        return preg_match('/\b(schedule|appointment|viewing|visit|callback|call me|afspraak|bezichtiging|terugbellen|rendez-vous|visite)\b/i', $question) === 1;
    }

    private function looksLikeGreetingOrSmallTalk(string $question): bool
    {
        $normalized = mb_strtolower(trim($question));

        if ($normalized === '') {
            return false;
        }

        return preg_match(
            '/^(?:(?:hi+|hello+|hey+|heya|hallo|bonjour|salut|good\s+(?:morning|afternoon|evening))|(?:how\s+are\s+you(?:\s+doing)?|how\s*r\s*u|are\s+you\s+there|hoe\s+gaat\s+het|wie\s+geht\s+es|ca\s+va))[\s!.?]*$/iu',
            $normalized
        ) === 1;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function contextSources(array $context): array
    {
        return array_values(array_filter([
            data_get($context, 'location.id') ? 'location:' . data_get($context, 'location.id') : null,
            data_get($context, 'yacht.id') ? 'yacht:' . data_get($context, 'yacht.id') : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $yacht
     */
    private function yachtSummaryFallback(array $yacht, string $language): string
    {
        $title = $yacht['name'] ?? trim(implode(' ', array_filter([$yacht['manufacturer'] ?? null, $yacht['model'] ?? null])));
        $parts = array_filter([
            $title,
            ! empty($yacht['year']) ? 'year ' . $yacht['year'] : null,
            ! empty($yacht['price']) ? 'price EUR ' . number_format((float) $yacht['price'], 0, '.', ',') : null,
            ! empty($yacht['location_city']) ? 'located in ' . $yacht['location_city'] : null,
        ]);

        $summary = implode(', ', $parts);

        return match ($language) {
            'nl' => $summary !== '' && ! empty($yacht['description'])
                ? "Ik kan alvast delen: {$summary}. {$yacht['description']}"
                : "Ik kan alvast delen wat ik hier zie over deze boot, maar voor extra details kan een medewerker ook verder helpen.",
            'de' => $summary !== '' && ! empty($yacht['description'])
                ? "Ich kann bereits Folgendes teilen: {$summary}. {$yacht['description']}"
                : "Ich kann bereits die hier verfuegbaren Bootsdaten teilen, und bei weiteren Fragen kann ein Mitarbeiter helfen.",
            'fr' => $summary !== '' && ! empty($yacht['description'])
                ? "Je peux deja partager ceci : {$summary}. {$yacht['description']}"
                : "Je peux deja partager les informations disponibles sur ce bateau, et un collaborateur peut aider pour plus de details.",
            default => $summary !== '' && ! empty($yacht['description'])
                ? "Here’s what I can already share: {$summary}. {$yacht['description']}"
                : "I can already share the boat details available here, and a team member can help with anything more specific.",
        };
    }

    private function scheduleFallback(string $language): string
    {
        return match ($language) {
            'nl' => 'Ik kan uw interesse meteen doorzetten. Een medewerker van deze locatie helpt u graag verder met een afspraak of bezichtiging.',
            'de' => 'Ich kann Ihr Interesse direkt weitergeben. Ein Mitarbeiter dieses Standorts hilft Ihnen gern mit einem Termin oder einer Besichtigung weiter.',
            'fr' => 'Je peux transmettre votre demande tout de suite. Un collaborateur de ce site vous aidera avec un rendez-vous ou une visite.',
            default => 'I can pass this on right away. A team member from this location can help you arrange a viewing or appointment.',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function greetingFallback(array $context, string $language): string
    {
        $locationName = trim((string) data_get($context, 'location.name', ''));
        $yachtName = trim((string) data_get($context, 'yacht.name', ''));

        return match ($language) {
            'nl' => $yachtName !== ''
                ? "Hallo, ik help u graag met vragen over {$yachtName}, zoals specificaties, prijs, beschikbaarheid en bezichtigingen. Wat wilt u weten?"
                : ($locationName !== ''
                    ? "Hallo, ik help u graag met vragen over {$locationName}, beschikbare jachten, marina diensten, opslag, prijzen en bezichtigingen. Wat wilt u weten?"
                    : 'Hallo, ik help u graag met vragen over onze jachten, marina diensten, opslag, prijzen en bezichtigingen. Wat wilt u weten?'),
            'de' => $yachtName !== ''
                ? "Hallo, ich helfe gern bei Fragen zu {$yachtName}, etwa zu Daten, Preis, Verfuegbarkeit und Besichtigungen. Was moechten Sie wissen?"
                : ($locationName !== ''
                    ? "Hallo, ich helfe gern bei Fragen zu {$locationName}, verfuegbaren Yachten, Marina-Services, Lagerung, Preisen und Besichtigungen. Was moechten Sie wissen?"
                    : 'Hallo, ich helfe gern bei Fragen zu unseren Yachten, Marina-Services, Lagerung, Preisen und Besichtigungen. Was moechten Sie wissen?'),
            'fr' => $yachtName !== ''
                ? "Bonjour, je peux vous aider avec des questions sur {$yachtName}, comme les caracteristiques, le prix, la disponibilite et les visites. Que souhaitez-vous savoir ?"
                : ($locationName !== ''
                    ? "Bonjour, je peux vous aider avec des questions sur {$locationName}, les yachts disponibles, les services de marina, le stockage, les prix et les visites. Que souhaitez-vous savoir ?"
                    : 'Bonjour, je peux vous aider avec des questions sur nos yachts, les services de marina, le stockage, les prix et les visites. Que souhaitez-vous savoir ?'),
            default => $yachtName !== ''
                ? "Hi, I can help with questions about {$yachtName}, including specifications, price, availability, and viewings. What would you like to know?"
                : ($locationName !== ''
                    ? "Hi, I can help with questions about {$locationName}, available yachts, marina services, storage, pricing, and viewings. What would you like to know?"
                    : 'Hi, I can help with questions about our yachts, marina services, storage, pricing, and viewings. What would you like to know?'),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function genericFallback(array $context, string $language): string
    {
        $locationName = trim((string) data_get($context, 'location.name', ''));

        return match ($language) {
            'nl' => $locationName !== ''
                ? "Dank voor uw bericht. Ik kon hiervoor nog geen betrouwbaar website-antwoord vinden voor {$locationName}. U kunt me vragen naar beschikbare jachten, marina diensten, opslag, prijzen of bezichtigingen, en een medewerker kan helpen als dat nodig is."
                : 'Dank voor uw bericht. Ik kon hiervoor nog geen betrouwbaar website-antwoord vinden. U kunt me vragen naar beschikbare jachten, marina diensten, opslag, prijzen of bezichtigingen, en een medewerker kan helpen als dat nodig is.',
            'de' => $locationName !== ''
                ? "Danke fuer Ihre Nachricht. Ich konnte dafuer noch keine verlaessliche Website-Antwort fuer {$locationName} finden. Sie koennen mich zu verfuegbaren Yachten, Marina-Services, Lagerung, Preisen oder Besichtigungen fragen, und ein Mitarbeiter kann helfen, wenn noetig."
                : 'Danke fuer Ihre Nachricht. Ich konnte dafuer noch keine verlaessliche Website-Antwort finden. Sie koennen mich zu verfuegbaren Yachten, Marina-Services, Lagerung, Preisen oder Besichtigungen fragen, und ein Mitarbeiter kann helfen, wenn noetig.',
            'fr' => $locationName !== ''
                ? "Merci pour votre message. Je n ai pas encore trouve de reponse fiable du site pour {$locationName}. Vous pouvez me poser des questions sur les yachts disponibles, les services de marina, le stockage, les prix ou les visites, et un collaborateur peut aider si besoin."
                : 'Merci pour votre message. Je n ai pas encore trouve de reponse fiable du site. Vous pouvez me poser des questions sur les yachts disponibles, les services de marina, le stockage, les prix ou les visites, et un collaborateur peut aider si besoin.',
            default => $locationName !== ''
                ? "Thanks for your message. I could not find a trusted website answer for that yet for {$locationName}. You can ask me about available yachts, marina services, storage, pricing, or viewings, and a team member can help if needed."
                : 'Thanks for your message. I could not find a trusted website answer for that yet. You can ask me about available yachts, marina services, storage, pricing, or viewings, and a team member can help if needed.',
        };
    }
}
