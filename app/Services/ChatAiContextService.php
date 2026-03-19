<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Yacht;
use App\Support\CopilotLanguage;
use Illuminate\Support\Str;

class ChatAiContextService
{
    public function __construct(
        private CopilotFaqService $faqService,
        private KnowledgeContextRetrievalService $relatedKnowledge,
        private CopilotLanguage $language
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Conversation $conversation, string $question, ?string $requestedLanguage = null): array
    {
        $conversation->loadMissing([
            'location',
            'contact',
            'lead',
            'boat.location',
        ]);

        $language = $this->language->normalize($requestedLanguage)
            ?? $this->language->normalize($conversation->language_preferred)
            ?? 'en';

        $yacht = $this->resolveYacht($conversation);
        $visibility = $this->isPublicWidgetConversation($conversation) ? 'public' : null;
        $knowledgeContext = array_filter([
            'language' => $language,
            'location_id' => $conversation->location_id,
            'visibility' => $visibility,
            'brand' => $yacht?->manufacturer,
            'model' => $yacht?->model,
        ], static fn ($value) => $value !== null && $value !== '');

        $faqKnowledge = $this->faqService->answer(
            $question,
            $conversation->location_id,
            $knowledgeContext,
            3
        );

        $relatedKnowledge = $this->relatedKnowledge->retrieve(
            $question,
            $conversation->location,
            $yacht
        );

        $recentMessages = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'sender_type', 'text', 'body', 'created_at'])
            ->reverse()
            ->map(fn ($message) => $this->compactArray([
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'text' => Str::limit((string) ($message->text ?: $message->body), 400, '...'),
                'created_at' => $message->created_at?->toIso8601String(),
            ]))
            ->values()
            ->all();

        $sources = [];
        if ($conversation->location_id) {
            $sources[] = 'location:' . $conversation->location_id;
        }
        if ($yacht?->id) {
            $sources[] = 'yacht:' . $yacht->id;
        }
        foreach ((array) data_get($faqKnowledge, 'answers.0.sources', []) as $source) {
            $faqId = $source['faq_id'] ?? $source['id'] ?? null;
            if ($faqId) {
                $sources[] = 'faq:' . $faqId;
            }
        }
        foreach ((array) data_get($relatedKnowledge, 'entities', []) as $entity) {
            $sourceRef = $entity['source_ref'] ?? null;
            $knowledgeEntityId = $entity['knowledge_entity_id'] ?? null;

            if ($sourceRef) {
                $sources[] = $sourceRef;
            }

            if ($knowledgeEntityId) {
                $sources[] = 'knowledge_entity:' . $knowledgeEntityId;
            }
        }

        return [
            'language' => $language,
            'conversation' => $this->compactArray([
                'id' => $conversation->id,
                'status' => $conversation->status,
                'priority' => $conversation->priority,
                'channel' => $conversation->channel,
                'channel_origin' => $conversation->channel_origin,
                'ai_mode' => $conversation->ai_mode ?: 'auto',
                'page_url' => $conversation->page_url,
            ]),
            'location' => $conversation->location ? $this->compactArray([
                'id' => $conversation->location->id,
                'name' => $conversation->location->name,
                'code' => $conversation->location->code,
                'chat_widget_welcome_text' => $conversation->location->chat_widget_welcome_text,
            ]) : null,
            'contact' => $conversation->contact ? $this->compactArray([
                'name' => $conversation->contact->name,
                'language_preferred' => $conversation->contact->language_preferred,
                'consent_service_messages' => $conversation->contact->consent_service_messages,
            ]) : null,
            'lead' => $conversation->lead ? $this->compactArray([
                'id' => $conversation->lead->id,
                'status' => $conversation->lead->status,
                'name' => $conversation->lead->name,
                'source_url' => $conversation->lead->source_url,
            ]) : null,
            'yacht' => $yacht ? $this->serializeYacht($yacht, $language) : null,
            'knowledge' => $this->serializeKnowledge($faqKnowledge, $relatedKnowledge),
            'recent_messages' => $recentMessages,
            'available_sources' => array_values(array_unique($sources)),
        ];
    }

    private function resolveYacht(Conversation $conversation): ?Yacht
    {
        if ($conversation->relationLoaded('boat') && $conversation->boat) {
            return $conversation->boat;
        }

        if ($conversation->boat_id) {
            return Yacht::query()
                ->with('location:id,name,code')
                ->find($conversation->boat_id);
        }

        if ($conversation->lead?->yacht_id) {
            return Yacht::query()
                ->with('location:id,name,code')
                ->find($conversation->lead->yacht_id);
        }

        $pageUrl = trim((string) $conversation->page_url);
        if ($pageUrl === '') {
            return null;
        }

        $lookupUrl = rtrim($pageUrl, '/');

        return Yacht::query()
            ->with('location:id,name,code')
            ->where(function ($query) use ($lookupUrl) {
                $query->where('external_url', $lookupUrl)
                    ->orWhere('external_url', $lookupUrl . '/');
            })
            ->first();
    }

    private function isPublicWidgetConversation(Conversation $conversation): bool
    {
        $channel = strtolower((string) ($conversation->channel ?? ''));
        $origin = strtolower((string) ($conversation->channel_origin ?? ''));

        return in_array($channel, ['web_widget'], true)
            || in_array($origin, ['web_widget'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeYacht(Yacht $yacht, string $language): array
    {
        $description = match ($language) {
            'nl' => $yacht->short_description_nl ?: $yacht->short_description_en,
            'de' => $yacht->short_description_de ?: $yacht->short_description_en,
            'fr' => $yacht->short_description_fr ?: $yacht->short_description_en,
            default => $yacht->short_description_en ?: $yacht->short_description_nl,
        };

        return $this->compactArray([
            'id' => $yacht->id,
            'name' => $yacht->boat_name ?: trim(implode(' ', array_filter([$yacht->manufacturer, $yacht->model]))),
            'manufacturer' => $yacht->manufacturer,
            'model' => $yacht->model,
            'year' => $yacht->year,
            'price' => $yacht->price,
            'status' => $yacht->status,
            'boat_type' => $yacht->boat_type,
            'boat_category' => $yacht->boat_category,
            'location_city' => $yacht->location_city,
            'external_url' => $yacht->external_url,
            'description' => $description ?: $yacht->owners_comment,
            'location' => $yacht->location ? $this->compactArray([
                'id' => $yacht->location->id,
                'name' => $yacht->location->name,
                'code' => $yacht->location->code,
            ]) : null,
            'specifications' => $this->compactArray([
                'loa' => $yacht->loa,
                'beam' => $yacht->beam,
                'draft' => $yacht->draft,
                'cabins' => $yacht->cabins,
                'berths' => $yacht->berths,
                'fuel' => $yacht->fuel,
                'horse_power' => $yacht->horse_power,
                'engine_manufacturer' => $yacht->engine_manufacturer,
                'engine_quantity' => $yacht->engine_quantity,
                'max_speed' => $yacht->max_speed,
                'cruising_speed' => $yacht->cruising_speed,
                'hull_type' => $yacht->hull_type,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $faqKnowledge
     * @param  array<string, mixed>  $relatedKnowledge
     * @return array<string, mixed>
     */
    private function serializeKnowledge(array $faqKnowledge, array $relatedKnowledge): array
    {
        $answer = $faqKnowledge['answers'][0] ?? null;
        $hasTrustedAnswer = is_array($answer) && ! ($answer['used_fallback'] ?? false);

        return [
            'strategy' => $faqKnowledge['strategy'] ?? null,
            'confidence' => $faqKnowledge['confidence'] ?? 0.0,
            'top_answer' => $hasTrustedAnswer ? $this->compactArray([
                'question' => $answer['question'] ?? null,
                'answer' => $answer['answer'] ?? null,
                'category' => $answer['category'] ?? null,
                'confidence' => $answer['confidence'] ?? null,
                'confidence_label' => $answer['confidence_label'] ?? null,
                'sources' => array_map(fn (array $source) => $this->compactArray([
                    'faq_id' => $source['faq_id'] ?? $source['id'] ?? null,
                    'question' => $source['question'] ?? null,
                    'category' => $source['category'] ?? null,
                    'brand' => $source['brand'] ?? null,
                    'model' => $source['model'] ?? null,
                    'location_id' => $source['location_id'] ?? null,
                ]), (array) ($answer['sources'] ?? [])),
            ]) : null,
            'related_entities' => array_values((array) ($relatedKnowledge['entities'] ?? [])),
            'related_matches' => array_values((array) ($relatedKnowledge['matches'] ?? [])),
            'trace' => $faqKnowledge['trace'] ?? null,
            'related_trace' => $relatedKnowledge['trace'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function compactArray(array $data): array
    {
        return array_filter($data, static fn ($value) => ! in_array($value, [null, '', []], true));
    }
}
