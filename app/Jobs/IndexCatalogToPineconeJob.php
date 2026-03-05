<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexCatalogToPineconeJob implements ShouldQueue
{
    use Queueable;

    protected $catalogId;

    public function __construct(int $catalogId)
    {
        $this->catalogId = $catalogId;
    }

    public function handle(): void
    {
        try {
            // 1. Fetch the rich joined record
            $boat = DB::table('boat_catalog')
                ->select([
                    'boat_catalog.*',
                    'brands.name as brand_name',
                    'models.name as model_name',
                    'boat_types.name as type_name',
                    'engine_brands.name as engine_name'
                ])
                ->leftJoin('brands', 'boat_catalog.brand_id', '=', 'brands.id')
                ->leftJoin('models', 'boat_catalog.model_id', '=', 'models.id')
                ->leftJoin('boat_types', 'boat_catalog.boat_type_id', '=', 'boat_types.id')
                ->leftJoin('engine_brands', 'boat_catalog.engine_brand_id', '=', 'engine_brands.id')
                ->where('boat_catalog.id', $this->catalogId)
                ->first();

            if (!$boat) return;

            // 2. Build the semantic text embedding string
            // The more human-readable this is, the better the Vector AI finds it.
            $embeddingText = "Brand: " . ($boat->brand_name ?? 'Unknown') . "\n";
            $embeddingText .= "Model: " . ($boat->model_name ?? 'Unknown') . "\n";
            $embeddingText .= "Type: " . ($boat->type_name ?? 'Unknown') . "\n";
            $embeddingText .= "Year: " . ($boat->year ?? 'Unknown') . "\n";
            $embeddingText .= "Length: " . ($boat->length ? $boat->length . 'm' : 'Unknown') . "\n";
            $embeddingText .= "Engine: " . ($boat->engine_name ?? 'Unknown') . "\n";
            $embeddingText .= "Description: " . ($boat->description ?? 'None') . "\n";

            // 3. Generate OpenAI Embedding (text-embedding-3-small)
            $openAiKey = env('OPENAI_API_KEY');
            if (empty($openAiKey)) {
                Log::warning("Skipping Pinecone Index because OPENAI_API_KEY is missing.");
                // For local sandbox dev allow silent drop so job doesn't loop
                return;
            }

            $embedResponse = Http::withToken($openAiKey)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => 'text-embedding-3-small',
                    'input' => $embeddingText,
                    'dimensions' => 1408
                ]);

            if (!$embedResponse->successful()) {
                throw new \Exception("OpenAI Embedding Failed: " . $embedResponse->body());
            }

            $vector = $embedResponse->json('data.0.embedding');

            // 4. Upsert to Pinecone
            $pineconeKey = env('PINECONE_API_KEY');
            $pineconeHost = env('PINECONE_HOST'); // e.g. "https://yachtshift-abc1234.svc.pinecone.io"

            if (empty($pineconeKey) || empty($pineconeHost)) {
                Log::warning("Skipping Pinecone Index because Pinecone credentials missing.");
                return;
            }

            $images = json_decode($boat->image_urls, true);
            $mainImg = is_array($images) && count($images) > 0 ? $images[0] : null;

            $url = str_starts_with($pineconeHost, 'http') ? "{$pineconeHost}/vectors/upsert" : "https://{$pineconeHost}/vectors/upsert";

            $metadata = [
                'catalog_id' => $boat->id,
                'brand_id' => $boat->brand_id,
                'brand_name' => $boat->brand_name,
                'model_name' => $boat->model_name,
                'type_name' => $boat->type_name,
                'year' => $boat->year,
                'length' => $boat->length !== null ? (float)$boat->length : null,
                'price' => $boat->price !== null ? (float)$boat->price : null,
                'quality_score' => $boat->quality_score,
                'main_image' => $mainImg
            ];

            // Pinecone does not allow null metadata values.
            $metadata = array_filter($metadata, function($value) {
                return $value !== null;
            });

            $pineconeResponse = Http::withHeaders([
                'Api-Key' => $pineconeKey,
                'Content-Type' => 'application/json'
            ])->post($url, [
                'vectors' => [
                    [
                        'id' => 'boat_' . $boat->id,
                        'values' => $vector,
                        'metadata' => $metadata
                    ]
                ]
            ]);

            if (!$pineconeResponse->successful()) {
                throw new \Exception("Pinecone Upsert Failed: " . $pineconeResponse->body());
            }

            // 5. Mark as successfully indexed
            DB::table('boat_catalog')->where('id', $this->catalogId)->update([
                'pinecone_status' => 'indexed'
            ]);

            Log::info("Successfully indexed boat {$this->catalogId} to Pinecone.");

        } catch (\Exception $e) {
            Log::error("Pinecone Job Exception for Boat {$this->catalogId}: " . $e->getMessage());
            DB::table('boat_catalog')->where('id', $this->catalogId)->update([
                'pinecone_status' => 'failed'
            ]);
            
            // Retrow to let the queue manager know it failed (so it backs off/retries)
            throw $e;
        }
    }
}
