<?php

namespace App\Services;

use App\Models\GlossaryTerm;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentAiTranslationService
{
    public function translateStructured(
        array $fields,
        string $sourceLocale,
        string $targetLocale,
        array $protectedTokens = [],
        ?string $contentType = null
    ): ?array {
        $schema = [];
        foreach ($fields as $key => $value) {
            $schema[$key] = 'string';
        }

        $glossary = GlossaryTerm::all();
        $glossaryText = $this->formatGlossary($glossary, $targetLocale);

        $typeHint = $contentType ? "Content type: {$contentType}." : 'Content type: general.';
        $protectedText = empty($protectedTokens)
            ? 'None.'
            : implode(', ', $protectedTokens);

        $payloadJson = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $prompt = <<<PROMPT
Translate the JSON fields from {$sourceLocale} to {$targetLocale}. Return ONLY valid JSON, no markdown.
{$typeHint}

Rules:
- Keep placeholders/tokens EXACTLY as provided.
- Do not invent facts or add new content.
- Preserve HTML tags if present.

Protected tokens (DO NOT translate):
{$protectedText}

Glossary (must follow):
{$glossaryText}

Input JSON:
{$payloadJson}

Output JSON schema:
{$this->schemaToJson($schema)}
PROMPT;

        return $this->callJsonModel($prompt, $schema);
    }

    private function schemaToJson(array $schema): string
    {
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function callJsonModel(string $prompt, array $schema): ?array
    {
        $provider = env('CONTENT_AI_PROVIDER', env('FAQ_AI_PROVIDER', env('ERROR_AI_PROVIDER', 'openai')));

        try {
            if ($provider === 'gemini') {
                return $this->callGeminiJson($prompt);
            }

            return $this->callOpenAiJson($prompt, $schema);
        } catch (\Throwable $e) {
            Log::warning('Content translation failed: ' . $e->getMessage());
            return null;
        }
    }

    private function callOpenAiJson(string $prompt, array $schema): ?array
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('CONTENT_AI_MODEL', env('FAQ_AI_MODEL', 'gpt-4o-mini'));
        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Return ONLY valid JSON. No markdown.'],
                    ['role' => 'user', 'content' => "Schema:\n" . json_encode($schema) . "\n\n" . $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 1200,
            ]);

        if ($response->failed()) {
            Log::warning('OpenAI content translation failed: ' . $response->body());
            return null;
        }

        $text = $response->json('choices.0.message.content');
        $decoded = json_decode((string) $text, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function callGeminiJson(string $prompt): ?array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('CONTENT_AI_MODEL', 'gemini-2.0-flash');
        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1200,
                    'responseMimeType' => 'application/json',
                ],
            ]
        );

        if (!$response->successful()) {
            Log::warning('Gemini content translation failed: ' . $response->body());
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $decoded = json_decode((string) $text, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function formatGlossary($glossary, string $language): string
    {
        if ($glossary->isEmpty()) {
            return 'No glossary terms provided.';
        }

        $lines = [];
        foreach ($glossary as $term) {
            $value = $term->{$language} ?: $term->term_key;
            $notes = $term->notes ? " ({$term->notes})" : '';
            $lines[] = "{$term->term_key} => {$value}{$notes}";
        }

        return implode("\n", $lines);
    }
}
