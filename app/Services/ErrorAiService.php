<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErrorAiService
{
    public function summarize(array $payload): ?array
    {
        $provider = env('ERROR_AI_PROVIDER', 'gemini');
        $payload = $this->sanitizePayload($payload);

        try {
            if ($provider === 'openai') {
                return $this->callOpenAi($payload);
            }

            return $this->callGemini($payload);
        } catch (\Exception $e) {
            Log::warning('Error AI failed: ' . $e->getMessage());
            return null;
        }
    }

    private function sanitizePayload(array $payload): array
    {
        $redact = ['password', 'token', 'api_key', 'secret', 'authorization', 'iban', 'card', 'credit_card'];
        array_walk_recursive($payload, function (&$value, $key) use ($redact) {
            foreach ($redact as $needle) {
                if (stripos((string) $key, $needle) !== false) {
                    $value = '[REDACTED]';
                    return;
                }
            }
        });
        return $payload;
    }

    private function callGemini(array $payload): ?array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('ERROR_AI_MODEL', 'gemini-2.5-flash-lite');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $system = "You are an assistant that returns STRICT JSON only. No markdown.";
        $user = [
            'error_type' => $payload['error_type'] ?? null,
            'message' => $payload['message'] ?? null,
            'source' => $payload['source'] ?? null,
            'route' => $payload['route'] ?? null,
            'action' => $payload['action'] ?? null,
            'http_status' => $payload['http_status'] ?? null,
            'tags' => $payload['tags'] ?? [],
            'release' => $payload['release'] ?? null,
            'environment' => $payload['environment'] ?? null,
        ];

        $prompt = [
            'category' => 'string',
            'severity' => 'low|medium|high|critical',
            'user_message' => [
                'nl' => 'string',
                'en' => 'string',
                'de' => 'string'
            ],
            'user_steps' => ['string'],
            'dev_summary' => 'string',
            'suggested_checks' => ['string']
        ];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($url, [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => "Analyze this error and return JSON matching this schema:\n" .
                        json_encode($prompt) . "\n\nInput:\n" . json_encode($user)
                ]]
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 800,
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Gemini error AI failed: ' . $response->body());
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (!$text) {
            return null;
        }

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function callOpenAi(array $payload): ?array
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('ERROR_AI_MODEL', 'gpt-4o-mini');
        $schema = [
            'category' => 'string',
            'severity' => 'low|medium|high|critical',
            'user_message' => [
                'nl' => 'string',
                'en' => 'string',
                'de' => 'string'
            ],
            'user_steps' => ['string'],
            'dev_summary' => 'string',
            'suggested_checks' => ['string']
        ];

        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Return ONLY valid JSON. No markdown.'],
                ['role' => 'user', 'content' => "Schema:\n" . json_encode($schema) . "\n\nInput:\n" . json_encode($payload)],
            ],
            'temperature' => 0.2,
        ]);

        if ($response->failed()) {
            Log::warning('OpenAI error AI failed: ' . $response->body());
            return null;
        }

        $text = $response->json('choices.0.message.content');
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
}
