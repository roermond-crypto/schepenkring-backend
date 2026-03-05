<?php

namespace App\Services;

use App\Models\ChatFaq;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatFaqPineconeService
{
    public function __construct(private FaqEmbeddingService $embeddingService)
    {
    }

    public function upsert(ChatFaq $faq): bool
    {
        $vector = $this->buildVector($faq);
        if (!$vector) {
            return false;
        }

        $payload = [
            'vectors' => [$vector],
            'namespace' => $this->namespace(),
        ];

        $response = $this->pineconeRequest('/vectors/upsert', $payload);
        if (!$response || $response->failed()) {
            $body = $response ? $response->body() : 'no response';
            Log::warning('Chat FAQ pinecone upsert failed: ' . $body);
            return false;
        }

        $faq->indexed_at = now();
        $faq->save();

        return true;
    }

    public function query(string $query, string $language, ?int $harborId = null, int $topK = 5): array
    {
        $embedding = $this->embeddingService->embed($query);
        if (!$embedding) {
            return [];
        }

        $filter = [
            'language' => ['$eq' => $language],
        ];
        if ($harborId) {
            $filter['harbor_id'] = ['$eq' => $harborId];
        }

        $payload = [
            'vector' => $embedding,
            'topK' => $topK,
            'includeMetadata' => true,
            'namespace' => $this->namespace(),
            'filter' => $filter,
        ];

        $response = $this->pineconeRequest('/query', $payload);
        if (!$response || $response->failed()) {
            $body = $response ? $response->body() : 'no response';
            Log::warning('Chat FAQ pinecone query failed: ' . $body);
            return [];
        }

        return $response->json('matches') ?? [];
    }

    private function buildVector(ChatFaq $faq): ?array
    {
        $text = trim($faq->question . "\n" . $faq->best_answer);
        $embedding = $this->embeddingService->embed($text);
        if (!$embedding) {
            return null;
        }

        return [
            'id' => $faq->id,
            'values' => $embedding,
            'metadata' => [
                'chat_faq_id' => $faq->id,
                'harbor_id' => $faq->harbor_id,
                'language' => $faq->language,
                'question' => $faq->question,
                'answer' => $faq->best_answer,
            ],
        ];
    }

    private function pineconeRequest(string $path, array $payload)
    {
        $host = env('PINECONE_HOST');
        if (!$host) {
            return null;
        }

        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://' . $host;
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

    private function namespace(): string
    {
        return env('PINECONE_CHAT_FAQ_NAMESPACE', 'chat_faq');
    }
}
