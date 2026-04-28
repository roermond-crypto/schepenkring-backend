<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiKnowledgeArticle;
use App\Services\ChatTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiKnowledgeArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'match_type' => 'nullable|string|max:40',
            'status' => 'nullable|string|max:20',
            'language' => 'nullable|string|max:8',
            'search' => 'nullable|string|max:120',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AiKnowledgeArticle::query()->with('createdBy');
        foreach (['match_type', 'status', 'language'] as $field) {
            if (!empty($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('match_value', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest()->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (AiKnowledgeArticle $article) => $this->serialize($article)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'match_type' => 'required|string|in:brand,model,boat_type,general',
            'match_value' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:60',
            'language' => 'nullable|string|max:8',
            'status' => 'nullable|string|in:active,draft,archived',
        ]);

        $article = AiKnowledgeArticle::create([
            ...$validated,
            'language' => $this->normalizeLanguage($validated['language'] ?? $request->header('X-Locale') ?? null),
            'status' => $validated['status'] ?? 'active',
            'created_by_user_id' => $request->user()?->id,
        ]);

        // Embed in Pinecone asynchronously (best-effort)
        $this->embedInPinecone($article);

        return response()->json([
            'message' => 'Knowledge article created.',
            'article' => $this->serialize($article->fresh('createdBy')),
        ], 201);
    }

    public function translate(Request $request, ChatTranslationService $translator): JsonResponse
    {
        $validated = $request->validate([
            'source_language' => 'required|string|max:8',
            'target_language' => 'required|string|max:8',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:60',
        ]);

        $sourceLanguage = $this->normalizeLanguage($validated['source_language']);
        $targetLanguage = $this->normalizeLanguage($validated['target_language']);

        $title = trim((string) ($validated['title'] ?? ''));
        $content = trim((string) ($validated['content'] ?? ''));
        $tags = collect($validated['tags'] ?? [])->map(fn ($tag) => trim((string) $tag))->filter()->values()->all();

        if ($sourceLanguage === $targetLanguage) {
            return response()->json([
                'title' => $title,
                'content' => $content,
                'tags' => $tags,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'translated' => false,
            ]);
        }

        try {
            $translatedTitle = $title !== '' ? $translator->translate($title, $targetLanguage, $sourceLanguage)['translated_text'] : '';
            $translatedContent = $content !== '' ? $translator->translate($content, $targetLanguage, $sourceLanguage)['translated_text'] : '';
        } catch (\Throwable $e) {
            Log::warning('Knowledge article translation failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Translation failed.'], 422);
        }

        return response()->json([
            'title' => trim((string) $translatedTitle),
            'content' => trim((string) $translatedContent),
            'tags' => $tags,
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'translated' => true,
        ]);
    }

    public function update(Request $request, AiKnowledgeArticle $article): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'match_type' => 'sometimes|required|string|in:brand,model,boat_type,general',
            'match_value' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:60',
            'language' => 'nullable|string|max:8',
            'status' => 'nullable|string|in:active,draft,archived',
        ]);

        if (array_key_exists('language', $validated)) {
            $validated['language'] = $this->normalizeLanguage($validated['language']);
        }

        $article->update($validated);

        // Re-embed with updated content
        $this->embedInPinecone($article);

        return response()->json([
            'message' => 'Knowledge article updated.',
            'article' => $this->serialize($article->fresh('createdBy')),
        ]);
    }

    public function destroy(AiKnowledgeArticle $article): JsonResponse
    {
        // Remove from Pinecone
        $this->removeFromPinecone($article);

        $article->delete();
        return response()->json(['message' => 'Knowledge article deleted.']);
    }

    public function seedStarterBrandsAndModels(Request $request): JsonResponse
    {
        $seeded = 0;
        $articles = [
            ['Princess Yachts - Premium Britse Vakmanschap', 'Princess Yachts is een toonaangevende Britse fabrikant van luxe jachten.', 'brand', 'Princess', 'nl'],
            ['Sunseeker - Britse Innovatie & Luxe', 'Sunseeker is een wereldberoemde Britse jachtbouwer opgericht in 1969.', 'brand', 'Sunseeker', 'nl'],
        ];

        foreach ($articles as [$title, $content, $matchType, $matchValue, $language]) {
            $article = AiKnowledgeArticle::updateOrCreate([
                'title' => $title,
                'match_type' => $matchType,
                'match_value' => $matchValue,
                'language' => $language,
            ], [
                'content' => $content,
                'status' => 'active',
            ]);

            if (!$article->pinecone_id) {
                $this->embedInPinecone($article);
                $seeded++;
            }
        }

        return response()->json([
            'message' => 'Starter brand and model articles have been upserted.',
            'embedded_attempts' => $seeded,
        ]);
    }

    // ── Pinecone Integration ──────────────────────────────────────────────

    private function embedInPinecone(AiKnowledgeArticle $article): void
    {
        try {
            $openAiKey = config('services.openai.key');
            $pineconeKey = config('services.pinecone.key');
            $pineconeHost = config('services.pinecone.host');

            if (!$openAiKey || !$pineconeKey || !$pineconeHost) {
                Log::warning('[KnowledgeArticle] Missing API keys for Pinecone embedding', [
                    'article_id' => $article->id,
                ]);
                return;
            }

            $embeddingText = $article->buildEmbeddingText();
            if (mb_strlen($embeddingText) < 10) {
                return;
            }

            // 1. Get embedding from OpenAI
            $embedResponse = Http::withToken($openAiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => config('services.openai.embedding_model', 'text-embedding-3-small'),
                    'input' => mb_substr($embeddingText, 0, 8000),
                    'dimensions' => (int) config('services.openai.embedding_dimensions', 1408),
                ]);

            if (!$embedResponse->successful()) {
                Log::error('[KnowledgeArticle] OpenAI embedding failed', [
                    'article_id' => $article->id,
                    'status' => $embedResponse->status(),
                ]);
                return;
            }

            $vector = $embedResponse->json('data.0.embedding');
            if (!$vector) {
                return;
            }

            // 2. Upsert to Pinecone
            $pineconeId = $article->pinecone_id ?: 'knowledge_' . $article->id;

            $pineconeResponse = Http::withHeaders([
                'Api-Key' => $pineconeKey,
                'Content-Type' => 'application/json',
            ])->timeout(12)->post("{$pineconeHost}/vectors/upsert", [
                'namespace' => config('services.pinecone.namespace', 'knowledge_library'),
                'vectors' => [[
                    'id' => $pineconeId,
                    'values' => $vector,
                    'metadata' => [
                        'article_id' => $article->id,
                        'type' => 'knowledge_article',
                        'match_type' => $article->match_type,
                        'match_value' => $article->match_value,
                        'tags' => is_array($article->tags) ? implode(',', $article->tags) : '',
                        'language' => $article->language,
                        'title' => mb_substr($article->title, 0, 200),
                    ],
                ]],
            ]);

            if ($pineconeResponse->successful()) {
                $article->updateQuietly([
                    'pinecone_id' => $pineconeId,
                    'last_embedded_at' => now(),
                ]);

                Log::info('[KnowledgeArticle] Embedded in Pinecone', [
                    'article_id' => $article->id,
                    'pinecone_id' => $pineconeId,
                ]);
            } else {
                Log::error('[KnowledgeArticle] Pinecone upsert failed', [
                    'article_id' => $article->id,
                    'status' => $pineconeResponse->status(),
                    'body' => $pineconeResponse->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[KnowledgeArticle] Pinecone embedding exception', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removeFromPinecone(AiKnowledgeArticle $article): void
    {
        try {
            if (!$article->pinecone_id) {
                return;
            }

            $pineconeKey = config('services.pinecone.key');
            $pineconeHost = config('services.pinecone.host');

            if (!$pineconeKey || !$pineconeHost) {
                return;
            }

            Http::withHeaders([
                'Api-Key' => $pineconeKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$pineconeHost}/vectors/delete", [
                'namespace' => config('services.pinecone.namespace', 'knowledge_library'),
                'ids' => [$article->pinecone_id],
            ]);

            Log::info('[KnowledgeArticle] Removed from Pinecone', [
                'article_id' => $article->id,
                'pinecone_id' => $article->pinecone_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[KnowledgeArticle] Pinecone delete failed', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function serialize(AiKnowledgeArticle $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'content' => $article->content,
            'match_type' => $article->match_type,
            'match_value' => $article->match_value,
            'tags' => $article->tags,
            'language' => $article->language,
            'status' => $article->status,
            'pinecone_id' => $article->pinecone_id,
            'last_embedded_at' => $article->last_embedded_at?->toIso8601String(),
            'created_by' => $article->createdBy ? [
                'id' => $article->createdBy->id,
                'name' => $article->createdBy->name,
            ] : null,
            'created_at' => $article->created_at?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];
    }

    private function normalizeLanguage(?string $language): string
    {
        $candidate = Str::lower((string) $language);
        $supported = config('locales.supported', ['nl', 'en', 'de']);

        return in_array($candidate, $supported, true) ? $candidate : config('locales.default', 'nl');
    }
}
