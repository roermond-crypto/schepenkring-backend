<?php

namespace App\Services;

use App\Models\FaqTranslation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaqPineconeService
{
    public function __construct(private FaqEmbeddingService $embeddingService)
    {
    }

    public function upsertTranslation(FaqTranslation $translation): bool
    {
        $vector = $this->buildVector($translation);
        if (!$vector) {
            return false;
        }

        $payload = [
            'vectors' => [$vector],
            'namespace' => $this->pineconeNamespace(),
        ];

        $response = $this->pineconeRequest('/vectors/upsert', $payload);
        if (!$response || $response->failed()) {
            $body = $response ? $response->body() : 'no response';
            Log::warning('Pinecone FAQ upsert failed: ' . $body);
            return false;
        }

        $translation->indexed_at = now();
        $translation->save();

        return true;
    }

    public function query(string $query, string $language, ?string $namespace = null, int $topK = 10, ?string $category = null): array
    {
        $embedding = $this->embeddingService->embed($query);
        if (!$embedding) {
            return [];
        }

        $filter = [
            'language' => ['$eq' => $language],
            'is_active' => ['$eq' => true],
        ];

        if ($namespace) {
            $filter['namespace'] = ['$eq' => $namespace];
        }
        if ($category) {
            $filter['category'] = ['$eq' => $category];
        }

        $payload = [
            'vector' => $embedding,
            'topK' => $topK,
            'includeMetadata' => true,
            'namespace' => $this->pineconeNamespace(),
            'filter' => $filter,
        ];

        $response = $this->pineconeRequest('/query', $payload);
        if (!$response || $response->failed()) {
            $body = $response ? $response->body() : 'no response';
            Log::warning('Pinecone FAQ query failed: ' . $body);
            return [];
        }

        return $response->json('matches') ?? [];
    }

    private function buildVector(FaqTranslation $translation): ?array
    {
        $faq = $translation->faq;
        if (!$faq) {
            return null;
        }

        $includeLong = filter_var(env('FAQ_INDEX_INCLUDE_LONG_DESCRIPTION', true), FILTER_VALIDATE_BOOL);
        $text = implode("\n", array_filter([
            $translation->question,
            $translation->answer,
            $includeLong ? $translation->long_description : null,
            trim(($faq->category ?? '') . ' / ' . ($faq->subcategory ?? '')),
            $faq->namespace ?? '',
        ]));

        $embedding = $this->embeddingService->embed($text);
        if (!$embedding) {
            return null;
        }

        return [
            'id' => $translation->id,
            'values' => $embedding,
            'metadata' => [
                'translation_id' => $translation->id,
                'faq_id' => $translation->faq_id,
                'language' => $translation->language,
                'namespace' => $faq->namespace,
                'category' => $faq->category,
                'subcategory' => $faq->subcategory,
                'slug' => $faq->slug,
                'question' => $translation->question,
                'answer' => $translation->answer,
                'is_active' => (bool) $faq->is_active,
            ],
        ];
    }

    private function pineconeRequest(string $path, array $payload)
    {
        $host = $this->pineconeHost();
        if (!$host) {
            return null;
        }

        $apiKey = env('PINECONE_API_KEY');
        if (!$apiKey) {
            return null;
        }

        return Http::withHeaders([
            'Api-Key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(rtrim($host, '/') . $path, $payload);
    }

    private function pineconeHost(): ?string
    {
        $host = env('PINECONE_HOST');
        if (!$host) {
            return null;
        }

        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://' . $host;
        }

        return $host;
    }

    private function pineconeNamespace(): string
    {
        return env('PINECONE_FAQ_NAMESPACE', 'faq');
    }
}
