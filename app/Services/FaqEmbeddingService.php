<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaqEmbeddingService
{
    public function embed(string $text): ?array
    {
        $provider = env('FAQ_EMBED_PROVIDER', env('ERROR_AI_PROVIDER', 'openai'));

        try {
            if ($provider === 'gemini') {
                return $this->embedWithGemini($text);
            }

            return $this->embedWithOpenAi($text);
        } catch (\Throwable $e) {
            Log::warning('FAQ embedding failed: ' . $e->getMessage());
            return null;
        }
    }

    private function embedWithOpenAi(string $text): ?array
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('FAQ_EMBED_MODEL', 'text-embedding-3-small');

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $model,
                'input' => $text,
            ]);

        if ($response->failed()) {
            Log::warning('OpenAI FAQ embedding failed: ' . $response->body());
            return null;
        }

        return $response->json('data.0.embedding');
    }

    private function embedWithGemini(string $text): ?array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $model = env('FAQ_EMBED_MODEL', 'embedding-001');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:embedContent?key={$apiKey}";

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post($url, [
                'content' => [
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Gemini FAQ embedding failed: ' . $response->body());
            return null;
        }

        return $response->json('embedding.values');
    }
}
