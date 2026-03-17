<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BoatFieldHelpGeneratorService
{
    private const SUPPORTED_LOCALES = ['nl', 'en', 'de'];

    public function generate(array $field): array
    {
        $apiKey = config('services.openai.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('OpenAI API key is not configured for help generation.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode("\n", [
                            'You write concise multilingual help text for yacht listing form fields.',
                            'Return strict JSON with the keys nl, en, and de.',
                            'Each value must be 1 or 2 short sentences.',
                            'Explain what the field means and what kind of value is expected.',
                            'If useful, include one short example in the help text, not as placeholder text.',
                            'Do not use markdown, bullets, or code fences.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'field' => [
                                'internal_key' => $field['internal_key'] ?? null,
                                'field_type' => $field['field_type'] ?? null,
                                'block_key' => $field['block_key'] ?? null,
                                'step_key' => $field['step_key'] ?? null,
                                'labels' => $field['labels_json'] ?? [],
                                'options' => $field['options_json'] ?? [],
                            ],
                            'output' => [
                                'nl' => 'Dutch help text',
                                'en' => 'English help text',
                                'de' => 'German help text',
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI help generation failed with status ' . $response->status() . '.');
        }

        $rawContent = (string) $response->json('choices.0.message.content', '');
        $decoded = json_decode($this->stripMarkdownJson($rawContent), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned invalid help text JSON.');
        }

        $generated = [];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $value = trim((string) ($decoded[$locale] ?? ''));
            if ($value !== '') {
                $generated[$locale] = $value;
            }
        }

        if ($generated === []) {
            throw new RuntimeException('OpenAI did not return any usable help text.');
        }

        return $generated;
    }

    private function stripMarkdownJson(string $content): string
    {
        $trimmed = trim($content);

        if (Str::startsWith($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }
}
