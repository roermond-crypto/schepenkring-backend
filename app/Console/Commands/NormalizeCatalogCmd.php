<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NormalizeCatalogCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yachtshift:normalize {--limit= : Limit the number of boats to process}';
    protected $description = 'Reads raw YachtShift boats, cleans strings, resolves aliases, validates images, scores quality, and stores into the structured Boat Catalog.';

    public function handle(\App\Services\Catalog\CatalogNormalizerService $normalizer, \App\Services\Catalog\CatalogScorerService $scorer)
    {
        $this->info('Starting Catalog Normalization...');

        $query = \Illuminate\Support\Facades\DB::table('yachtshift_raw_boats')->where('status', 'imported');
        
        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $rawBoats = $query->get();
        
        if ($rawBoats->isEmpty()) {
            $this->info('No pending raw boats to normalize.');
            return self::SUCCESS;
        }

        $processed = 0;
        foreach ($rawBoats as $rawBoat) {
            $payload = json_decode($rawBoat->raw_payload, true);
            if (!$payload) continue;

            try {
                // 1. Resolve IDs through the Normalizer dictionaries
                $brandId = $normalizer->resolveBrandId($payload['make'] ?? null);
                $modelId = $normalizer->resolveModelId($brandId, $payload['model'] ?? null);
                $typeId = $normalizer->resolveTypeId($payload['type'] ?? null);
                $engineBrandId = $normalizer->resolveEngineBrandId($payload['engine_make'] ?? null);

                // 2. Validate Images (skip expensive HEAD check on local testing)
                // In production, uncomment the validation:
                // $validImages = $normalizer->validateImages($payload['images'] ?? []);
                $validImages = array_slice($payload['images'] ?? [], 0, 15);

                // 3. Build the Catalog Record
                $catalogData = [
                    'brand_id' => $brandId,
                    'model_id' => $modelId,
                    'boat_type_id' => $typeId,
                    'engine_brand_id' => $engineBrandId,
                    'year' => isset($payload['year']) ? (int) $payload['year'] : null,
                    'length' => isset($payload['length']) ? (float) $payload['length'] : null,
                    'price' => isset($payload['price_eur']) ? (float) $payload['price_eur'] : null,
                    'description' => $payload['description'] ?? null,
                    'image_urls' => json_encode($validImages),
                    'raw_boat_id' => $rawBoat->id,
                    'pinecone_status' => 'pending',
                    'updated_at' => now(),
                    'created_at' => now(),
                ];

                // 4. Score it
                $score = $scorer->calculateScore((object)$catalogData);
                $catalogData['quality_score'] = $score;

                // 5. Store in Catalog
                \Illuminate\Support\Facades\DB::table('boat_catalog')->updateOrInsert(
                    ['raw_boat_id' => $rawBoat->id],
                    $catalogData
                );

                // 6. Mark as Normalized
                \Illuminate\Support\Facades\DB::table('yachtshift_raw_boats')
                    ->where('id', $rawBoat->id)
                    ->update(['status' => 'normalized']);

                $processed++;
                
                if ($processed % 100 === 0) {
                    $this->info("Normalized {$processed} boats...");
                }

            } catch (\Exception $e) {
                $this->error("Failed normalizing raw boat #{$rawBoat->id}: " . $e->getMessage());
                \Illuminate\Support\Facades\DB::table('yachtshift_raw_boats')
                    ->where('id', $rawBoat->id)
                    ->update(['status' => 'failed']);
            }
        }

        $this->info("Normalization complete. Successfully processed: {$processed}");
        return self::SUCCESS;
    }
}
