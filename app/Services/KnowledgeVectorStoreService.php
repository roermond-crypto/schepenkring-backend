<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KnowledgeVectorStoreService
{
    public function upsertText(string $id, string $text, array $metadata = [], ?string $namespace = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $vector = $this->embed($text);
        if (! $vector) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Api-Key' => (string) config('services.pinecone.key'),
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($this->endpoint('/vectors/upsert'), [
                'namespace' => $this->namespace($namespace),
                'vectors' => [[
                    'id' => Str::limit($id, 200, ''),
                    'values' => $vector,
                    'metadata' => array_merge($metadata, [
                        'text' => Str::limit($text, 1000, ''),
                    ]),
                ]],
            ]);

            if ($response->failed()) {
                Log::warning('Knowledge vector store upsert failed', ['status' => $response->status()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Knowledge vector store upsert exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $text, int $topK = 5, array $filter = [], ?string $namespace = null): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $vector = $this->embed($text);
        if (! $vector) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Api-Key' => (string) config('services.pinecone.key'),
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($this->endpoint('/query'), array_filter([
                'vector' => $vector,
                'topK' => $topK,
                'includeMetadata' => true,
                'namespace' => $this->namespace($namespace),
                'filter' => empty($filter) ? null : $filter,
            ], static fn ($value) => $value !== null));

            if ($response->failed()) {
                Log::warning('Knowledge vector store search failed', ['status' => $response->status()]);

                return [];
            }

            return collect($response->json('matches') ?? [])
                ->map(fn (array $match) => [
                    'id' => $match['id'] ?? null,
                    'score' => (float) ($match['score'] ?? 0),
                    'metadata' => $match['metadata'] ?? [],
                ])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Knowledge vector store search exception', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function delete(string $id, ?string $namespace = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Api-Key' => (string) config('services.pinecone.key'),
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($this->endpoint('/vectors/delete'), [
                'namespace' => $this->namespace($namespace),
                'ids' => [Str::limit($id, 200, '')],
            ]);

            if ($response->failed()) {
                Log::warning('Knowledge vector store delete failed', ['status' => $response->status()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Knowledge vector store delete exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.pinecone.key')
            && (bool) config('services.pinecone.host')
            && (bool) config('services.openai.key');
    }

    /**
     * @return array<int, float>|null
     */
    public function embed(string $text): ?array
    {
        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->timeout(15)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => 'text-embedding-3-small',
                    'input' => $text,
                ]);

            if ($response->failed()) {
                Log::warning('Knowledge vector store embedding failed', ['status' => $response->status()]);

                return null;
            }

            $vector = $response->json('data.0.embedding');

            return is_array($vector) ? $vector : null;
        } catch (\Throwable $e) {
            Log::warning('Knowledge vector store embedding exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.pinecone.host'), '/') . $path;
    }

    private function namespace(?string $namespace = null): string
    {
        $resolved = trim((string) ($namespace ?: config('services.pinecone.namespace', 'copilot')));

        return $resolved !== '' ? $resolved : 'copilot';
    }
}
