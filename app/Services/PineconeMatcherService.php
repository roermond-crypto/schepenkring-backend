<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pinecone Matcher Service — matches yachts against Pinecone vector DB.
 * Builds consensus values from similar boats in the feed database.
 */
class PineconeMatcherService
{
    private ?string $pineconeKey;
    private ?string $pineconeHost;
    private ?string $openAiKey;

    public function __construct()
    {
        $this->pineconeKey  = config('services.pinecone.key');
        $this->pineconeHost = config('services.pinecone.host');
        $this->openAiKey    = config('services.openai.key');
    }

    /**
     * Match the given form values against Pinecone and build consensus.
     *
     * @param  array  $formValues  Current extracted form values
     * @param  string|null  $hintText  Optional hint text from the user
     * @return array  ['consensus_values' => [], 'field_confidence' => [], 'field_sources' => [], 'top_matches' => [], 'warnings' => []]
     */
    public function matchAndBuildConsensus(array $formValues, ?string $hintText = null): array
    {
        $result = [
            'consensus_values'  => [],
            'field_confidence'  => [],
            'field_sources'     => [],
            'top_matches'       => [],
            'warnings'          => [],
        ];

        if (!$this->pineconeKey || !$this->pineconeHost || !$this->openAiKey) {
            Log::info('[PineconeMatcher] Skipped: missing API keys (PINECONE_API_KEY, PINECONE_HOST, or OPENAI_API_KEY)');
            return $result;
        }

        try {
            // Build search text from form values + hint
            $searchParts = array_filter([
                $formValues['manufacturer'] ?? null,
                $formValues['model'] ?? null,
                $formValues['boat_name'] ?? null,
                $formValues['boat_type'] ?? null,
                $hintText,
            ]);

            $searchText = implode(' ', $searchParts);

            if (strlen(trim($searchText)) < 3) {
                Log::info('[PineconeMatcher] Not enough context for search');
                return $result;
            }

            // Step 1: Generate embedding via OpenAI
            $embedResponse = Http::withToken($this->openAiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model'      => 'text-embedding-3-small',
                    'input'      => $searchText,
                    'dimensions' => 1408,
                ]);

            if (!$embedResponse->successful()) {
                Log::warning('[PineconeMatcher] Embedding failed: ' . $embedResponse->status());
                return $result;
            }

            $vector = $embedResponse->json('data.0.embedding');
            if (!$vector) {
                return $result;
            }

            // Step 2: Query Pinecone
            $pineconeResponse = Http::withHeaders([
                'Api-Key'      => $this->pineconeKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->pineconeHost}/query", [
                'vector'          => $vector,
                'topK'            => 5,
                'includeMetadata' => true,
            ]);

            if (!$pineconeResponse->successful()) {
                Log::warning('[PineconeMatcher] Pinecone query failed: ' . $pineconeResponse->status());
                return $result;
            }

            $matches = $pineconeResponse->json('matches') ?? [];

            if (empty($matches)) {
                Log::info('[PineconeMatcher] No matches found');
                return $result;
            }

            // Step 3: Build consensus from top matches
            $result['top_matches'] = array_map(fn($m) => [
                'score' => round(($m['score'] ?? 0) * 100),
                'boat'  => $m['metadata'] ?? [],
            ], $matches);

            // Find fields where majority of matches agree
            $fieldCounts = [];
            $fieldValues = [];

            foreach ($matches as $match) {
                $meta = $match['metadata'] ?? [];
                foreach ($meta as $field => $value) {
                    if ($value === null || $value === '') continue;
                    $fieldCounts[$field][$value] = ($fieldCounts[$field][$value] ?? 0) + 1;
                    $fieldValues[$field][] = $value;
                }
            }

            foreach ($fieldCounts as $field => $valueCounts) {
                arsort($valueCounts);
                $topValue = array_key_first($valueCounts);
                $topCount = $valueCounts[$topValue];
                $total = array_sum($valueCounts);

                // Only accept if majority agrees (>= 50%)
                if ($topCount >= ceil($total / 2)) {
                    $confidence = min(0.98, 0.80 + ($topCount / $total) * 0.18);
                    $result['consensus_values'][$field] = $topValue;
                    $result['field_confidence'][$field]  = round($confidence, 2);
                    $result['field_sources'][$field]      = 'pinecone_consensus';
                }
            }
            Log::info('[PineconeMatcher] Found ' . count($matches) . ' matches, ' . count($result['consensus_values']) . ' consensus fields');

            return $result;

        } catch (\Exception $e) {
            Log::error('[PineconeMatcher] Exception: ' . $e->getMessage());
            $result['warnings'][] = 'Pinecone matching failed: ' . $e->getMessage();
            return $result;
        }
    }

    /**
     * Upsert a yacht into Pinecone.
     *
     * @param  Yacht  $yacht
     * @return bool
     */
    public function upsertYacht(\App\Models\Yacht $yacht): bool
    {
        if (!$this->pineconeKey || !$this->pineconeHost || !$this->openAiKey) {
            Log::info('[PineconeMatcher] Indexing skipped: missing API keys');
            return false;
        }

        try {
            // Build text representation for embedding
            $metadata = $this->buildMetadata($yacht);
            $searchText = $this->buildSearchText($yacht, $metadata);

            // Step 1: Generate embedding
            $embedResponse = Http::withToken($this->openAiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model'      => 'text-embedding-3-small',
                    'input'      => $searchText,
                    'dimensions' => 1408,
                ]);

            if (!$embedResponse->successful()) {
                Log::warning('[PineconeMatcher] Embedding failed: ' . $embedResponse->status());
                return false;
            }

            $vector = $embedResponse->json('data.0.embedding');
            if (!$vector) return false;

            // Step 2: Upsert into Pinecone
            $pineconeResponse = Http::withHeaders([
                'Api-Key'      => $this->pineconeKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->pineconeHost}/vectors/upsert", [
                'vectors' => [
                    [
                        'id'       => (string) $yacht->id,
                        'values'   => $vector,
                        'metadata' => $metadata,
                    ]
                ]
            ]);

            if (!$pineconeResponse->successful()) {
                Log::warning('[PineconeMatcher] Pinecone upsert failed: ' . $pineconeResponse->status() . ' ' . $pineconeResponse->body());
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('[PineconeMatcher] Upsert exception: ' . $e->getMessage());
            return false;
        }
    }

    private function buildMetadata(\App\Models\Yacht $yacht): array
    {
        $data = $yacht->toArray();
        
        // Pick fields that are useful for metadata filtering/search
        $meta = [
            'id'                  => (string) $yacht->id,
            'boat_name'           => $yacht->boat_name,
            'manufacturer'        => $yacht->manufacturer,
            'model'               => $yacht->model,
            'year'                => (int) $yacht->year,
            'boat_type'           => $yacht->boat_type,
            'boat_category'       => $yacht->boat_category,
            'status'              => $yacht->status,
            'vessel_lying'        => $yacht->vessel_lying,
            'price'               => (float) $yacht->price,
            'loa'                 => (float) ($data['loa'] ?? 0),
            'beam'                => (float) ($data['beam'] ?? 0),
            'draft'               => (float) ($data['draft'] ?? 0),
            'engine_manufacturer' => $data['engine_manufacturer'] ?? null,
            'fuel'                => $data['fuel'] ?? null,
            'source'              => $data['source'] ?? null,
        ];

        return array_filter($meta, fn($v) => !is_null($v));
    }

    private function buildSearchText(\App\Models\Yacht $yacht, array $metadata): string
    {
        $parts = [
            $yacht->boat_name,
            $yacht->manufacturer,
            $yacht->model,
            $yacht->year,
            $yacht->boat_type,
            $yacht->boat_category,
            $yacht->vessel_lying,
            $yacht->short_description_nl,
            $yacht->owners_comment, // AI summary stored here
        ];

        return implode(' ', array_filter($parts));
    }
}
