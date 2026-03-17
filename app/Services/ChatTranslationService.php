<?php

namespace App\Services;

use App\Support\CopilotLanguage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ChatTranslationService
{
    public function __construct(private CopilotLanguage $language)
    {
    }

    public function translate(string $text, string $targetLanguage, ?string $sourceLanguage = null): array
    {
        $text = trim($text);
        if ($text === '') {
            throw ValidationException::withMessages([
                'text' => 'Text is required.',
            ]);
        }

        $target = $this->normalizeLanguageInput($targetLanguage);
        if (! $target) {
            throw ValidationException::withMessages([
                'target_language' => 'Unsupported target language.',
            ]);
        }

        $source = $this->normalizeLanguageInput($sourceLanguage)
            ?? $this->language->detectFromText($text)
            ?? $this->language->fromAcceptLanguage(request()->header('Accept-Language'))
            ?? 'en';

        if ($source === $target) {
            return [
                'original_text' => $text,
                'translated_text' => $text,
                'source_language' => $source,
                'target_language' => $target,
                'provider' => 'openai',
                'model' => $this->model(),
            ];
        }

        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI is not configured for chat translation.');
        }

        $response = Http::withToken($apiKey)
            ->timeout((int) config('services.openai.translation_timeout', 30))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model(),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a strict translation engine for a live chat composer. Return only valid JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($text, $source, $target),
                    ],
                ],
                'temperature' => 0.1,
            ]);

        if ($response->failed()) {
            Log::warning('Chat translation request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Chat translation provider failed.');
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $decoded = $this->decodeJson($content);
        if (! is_array($decoded) || ! is_string($decoded['translated_text'] ?? null)) {
            throw new RuntimeException('Chat translation provider returned invalid content.');
        }

        return [
            'original_text' => $text,
            'translated_text' => trim((string) $decoded['translated_text']),
            'source_language' => $this->normalizeLanguageInput($decoded['source_language'] ?? null) ?? $source,
            'target_language' => $target,
            'provider' => 'openai',
            'model' => $this->model(),
        ];
    }

    private function buildPrompt(string $text, string $source, string $target): string
    {
        $sourceLabel = $this->languageLabel($source);
        $targetLabel = $this->languageLabel($target);

        return <<<PROMPT
Translate the following live chat message from {$sourceLabel} to {$targetLabel}.

Rules:
- Preserve intent, tone, and formatting.
- Do not add explanations, greetings, or quotes.
- Keep names, boat names, phone numbers, and URLs unchanged.
- Return ONLY valid JSON using this schema:
  {"translated_text":"string","source_language":"{$source}"}

Message:
{$text}
PROMPT;
    }

    private function decodeJson(string $content): ?array
    {
        $content = trim($content);
        $content = preg_replace('/^```json/i', '', $content);
        $content = preg_replace('/```$/', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeLanguageInput(?string $value): ?string
    {
        $normalized = $this->language->normalize($value);
        if ($normalized) {
            return $normalized;
        }

        if (! is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'dutch', 'nederlands', 'hollands' => 'nl',
            'english', 'engels' => 'en',
            'german', 'deutsch', 'duits', 'germany' => 'de',
            'french', 'francais', 'français', 'frans' => 'fr',
            default => null,
        };
    }

    private function languageLabel(string $language): string
    {
        return match ($language) {
            'nl' => 'Dutch',
            'de' => 'German',
            'fr' => 'French',
            default => 'English',
        };
    }

    private function model(): string
    {
        return (string) config('services.openai.translation_model', 'gpt-4o-mini');
    }
}
