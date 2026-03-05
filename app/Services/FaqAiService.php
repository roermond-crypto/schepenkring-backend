<?php

namespace App\Services;

use App\Models\FaqTranslation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaqAiService
{
    public function generateLongDescription(FaqTranslation $translation, array $glossary, int $minWords, int $maxWords): ?string
    {
        $prompt = $this->longDescriptionPrompt($translation, $glossary, $minWords, $maxWords);
        return $this->callTextModel($prompt);
    }

    public function translate(FaqTranslation $source, string $targetLanguage, array $glossary): ?array
    {
        $prompt = $this->translationPrompt($source, $targetLanguage, $glossary);
        $response = $this->callJsonModel($prompt, [
            'question' => 'string',
            'answer' => 'string',
        ]);

        if (!is_array($response)) {
            return null;
        }

        if (empty($response['question']) || empty($response['answer'])) {
            return null;
        }

        return [
            'question' => trim((string) $response['question']),
            'answer' => trim((string) $response['answer']),
        ];
    }

    private function longDescriptionPrompt(FaqTranslation $translation, array $glossary, int $minWords, int $maxWords): string
    {
        $faq = $translation->faq;
        $glossaryText = $this->formatGlossary($glossary, $translation->language);

        return <<<PROMPT
You are writing a "Read more" explanation for a FAQ entry. Write {$minWords}-{$maxWords} words in {$translation->language}.

Structure the output in 3 short paragraphs with clear headings:
1) What it means
2) How it works in Nautic Secure
3) Example scenario

Rules:
- Stay concrete and consistent with the short answer.
- Use the same terminology from the glossary.
- No filler, no marketing hype.

FAQ Context:
Namespace: {$faq?->namespace}
Category: {$faq?->category}
Subcategory: {$faq?->subcategory}
Question: {$translation->question}
Short answer: {$translation->answer}

Glossary (must follow):
{$glossaryText}
PROMPT;
    }

    private function translationPrompt(FaqTranslation $source, string $targetLanguage, array $glossary): string
    {
        $faq = $source->faq;
        $glossaryText = $this->formatGlossary($glossary, $targetLanguage);

        return <<<PROMPT
Translate the FAQ into {$targetLanguage}. Keep terminology consistent with the glossary. Return ONLY valid JSON.

FAQ Context:
Namespace: {$faq?->namespace}
Category: {$faq?->category}
Subcategory: {$faq?->subcategory}

Source language: {$source->language}
Question: {$source->question}
Answer: {$source->answer}

Glossary (must follow):
{$glossaryText}

JSON schema:
{"question": "string", "answer": "string"}
PROMPT;
    }

    private function callTextModel(string $prompt): ?string
    {
        $provider = env('FAQ_AI_PROVIDER', env('ERROR_AI_PROVIDER', 'openai'));

        try {
            if ($provider === 'gemini') {
                return $this->callGeminiText($prompt);
            }

            return $this->callOpenAiText($prompt);
        } catch (\Throwable $e) {
            Log::warning('FAQ AI text failed: ' . $e->getMessage());
            return null;
        }
    }

    private function callJsonModel(string $prompt, array $schema): ?array
    {
        $provider = env('FAQ_AI_PROVIDER', env('ERROR_AI_PROVIDER', 'openai'));

        try {
            if ($provider === 'gemini') {
                return $this->callGeminiJson($prompt, $schema);
            }

            return $this->callOpenAiJson($prompt, $schema);
        } catch (\Throwable $e) {
            Log::warning('FAQ AI json failed: ' . $e->getMessage());
            return null;
        }
    }

    private function callOpenAiText(string $prompt): ?string
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('FAQ_AI_MODEL', 'gpt-4o-mini');
        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a precise technical writer.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

        if ($response->failed()) {
            Log::warning('OpenAI FAQ text failed: ' . $response->body());
            return null;
        }

        $text = $response->json('choices.0.message.content');
        $usage = $response->json('usage.total_tokens');
        if ($usage) {
            Log::info('FAQ long_description tokens', ['total_tokens' => $usage]);
        }

        return $text ? trim($text) : null;
    }

    private function callOpenAiJson(string $prompt, array $schema): ?array
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('FAQ_AI_MODEL', 'gpt-4o-mini');
        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Return ONLY valid JSON. No markdown.'],
                    ['role' => 'user', 'content' => "Schema:\n" . json_encode($schema) . "\n\n" . $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 500,
            ]);

        if ($response->failed()) {
            Log::warning('OpenAI FAQ json failed: ' . $response->body());
            return null;
        }

        $text = $response->json('choices.0.message.content');
        $usage = $response->json('usage.total_tokens');
        if ($usage) {
            Log::info('FAQ translation tokens', ['total_tokens' => $usage]);
        }

        $decoded = json_decode((string) $text, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function callGeminiText(string $prompt): ?string
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('FAQ_AI_MODEL', 'gemini-2.5-flash-lite');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)
            ->post($url, [
                'system_instruction' => [
                    'parts' => [[
                        'text' => 'You are a precise technical writer.'
                    ]],
                ],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => $prompt,
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 500,
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Gemini FAQ text failed: ' . $response->body());
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $usage = $response->json('usageMetadata.totalTokenCount');
        if ($usage) {
            Log::info('FAQ long_description tokens', ['total_tokens' => $usage]);
        }

        return $text ? trim($text) : null;
    }

    private function callGeminiJson(string $prompt, array $schema): ?array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('FAQ_AI_MODEL', 'gemini-2.5-flash-lite');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)
            ->post($url, [
                'system_instruction' => [
                    'parts' => [[
                        'text' => 'Return ONLY valid JSON. No markdown.'
                    ]],
                ],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => "Schema:\n" . json_encode($schema) . "\n\n" . $prompt,
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 500,
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Gemini FAQ json failed: ' . $response->body());
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $usage = $response->json('usageMetadata.totalTokenCount');
        if ($usage) {
            Log::info('FAQ translation tokens', ['total_tokens' => $usage]);
        }

        $decoded = json_decode((string) $text, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function formatGlossary(array $glossary, string $language): string
    {
        if (empty($glossary)) {
            return 'No glossary terms provided.';
        }

        $lines = [];
        foreach ($glossary as $term) {
            $value = $term[$language] ?? null;
            $line = $term['term_key'] . ': ' . ($value ?: '');
            if (!empty($term['notes'])) {
                $line .= ' (' . $term['notes'] . ')';
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
