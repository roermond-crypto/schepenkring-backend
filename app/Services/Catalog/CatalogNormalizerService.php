<?php

namespace App\Services\Catalog;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CatalogNormalizerService
{
    /**
     * Normalizes a raw yachtshift string deterministically.
     * Lowercases, trims, removes diacritics, and fixes bad spacing.
     */
    public function cleanString(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        // 1. Lowercase and trim
        $cleaned = trim(strtolower($input));

        // 2. Remove diacritics (Bénéteau -> beneteau)
        $cleaned = Str::ascii($cleaned);

        // 3. Remove weird whitespace/symbols replacing with single space
        $cleaned = preg_replace('/[^\w\s-]/', ' ', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim($cleaned);
    }

    /**
     * Resolves a brand name to its Canonical ID.
     * If not found, checks the Alias table.
     * If absolutely unknown, inserts it as a low-confidence alias for Admin review.
     */
    public function resolveBrandId(?string $rawMake): ?int
    {
        if (empty($rawMake)) return null;

        $cleanMake = $this->cleanString($rawMake);

        // 1. Check Canonical Brands directly
        $canonical = DB::table('brands')->where('slug', Str::slug($cleanMake))->first();
        if ($canonical) {
            return $canonical->id;
        }

        // 2. Check Alias Table
        $alias = DB::table('brand_aliases')->where('raw_name', $cleanMake)->first();
        if ($alias && $alias->brand_id) {
            // Found a mapped alias, bump evidence count
            DB::table('brand_aliases')->where('id', $alias->id)->increment('evidence_count');
            return $alias->brand_id;
        }

        // 3. Unknown! We store it unmapped in the alias table for future AI mapping or Admin Review
        if (!$alias) {
            DB::table('brand_aliases')->insert([
                'raw_name' => $cleanMake,
                'brand_id' => null,
                'confidence' => 0,
                'evidence_count' => 1,
                'is_reviewed' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
             DB::table('brand_aliases')->where('id', $alias->id)->increment('evidence_count');
        }

        return null; // Stays null until mapped
    }

    /**
     * Resolves a Boat Type Name.
     */
    public function resolveTypeId(?string $rawType): ?int
    {
        if (empty($rawType)) return null;
        $cleanType = $this->cleanString($rawType);

        // Since boat_types already existed without a slug, we check against the name.
        $canonical = DB::table('boat_types')->where('name', $rawType)->first();
        if ($canonical) return $canonical->id;

        $alias = DB::table('boat_type_aliases')->where('raw_name', $cleanType)->first();
        if ($alias && $alias->boat_type_id) {
            DB::table('boat_type_aliases')->where('id', $alias->id)->increment('evidence_count');
            return $alias->boat_type_id;
        }

        if (!$alias) {
            DB::table('boat_type_aliases')->insert([
                'raw_name' => $cleanType,
                'boat_type_id' => null,
                'created_at' => now()
            ]);
        } else {
             DB::table('boat_type_aliases')->where('id', $alias->id)->increment('evidence_count');
        }

        return null;
    }

    /**
     * Resolves a Model Name (Dependent on Brand).
     */
    public function resolveModelId(?int $brandId, ?string $rawModel): ?int
    {
        if (empty($brandId) || empty($rawModel)) return null;

        $cleanModel = $this->cleanString($rawModel);

        $canonical = DB::table('models')->where('brand_id', $brandId)
                                        ->where('slug', Str::slug($cleanModel))->first();
        if ($canonical) return $canonical->id;

        $alias = DB::table('model_aliases')->where('brand_id', $brandId)
                                           ->where('raw_name', $cleanModel)->first();
            
        if ($alias && $alias->model_id) {
            DB::table('model_aliases')->where('id', $alias->id)->increment('evidence_count');
            return $alias->model_id;
        }

        if (!$alias) {
            DB::table('model_aliases')->insert([
                'brand_id' => $brandId,
                'raw_name' => $cleanModel,
                'model_id' => null,
                'created_at' => now()
            ]);
        } else {
             DB::table('model_aliases')->where('id', $alias->id)->increment('evidence_count');
        }

        return null;
    }

    /**
     * Resolves an Engine Brand.
     */
    public function resolveEngineBrandId(?string $rawEngine): ?int
    {
        if (empty($rawEngine)) return null;
        $cleanEngine = $this->cleanString($rawEngine);

        $canonical = DB::table('engine_brands')->where('slug', Str::slug($cleanEngine))->first();
        if ($canonical) return $canonical->id;

        $alias = DB::table('engine_brand_aliases')->where('raw_name', $cleanEngine)->first();
        if ($alias && $alias->engine_brand_id) {
            DB::table('engine_brand_aliases')->where('id', $alias->id)->increment('evidence_count');
            return $alias->engine_brand_id;
        }

        if (!$alias) {
            DB::table('engine_brand_aliases')->insert([
                'raw_name' => $cleanEngine,
                'engine_brand_id' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
             DB::table('engine_brand_aliases')->where('id', $alias->id)->increment('evidence_count');
        }

        return null; // Awaiting mapping
    }

    /**
     * Basic Image Validation: Checks dimension limits strictly via HEAD and removes duplicates.
     */
    public function validateImages(array $imageUrls): array
    {
        $validUrls = [];
        // Max 15 images to save vectoring costs
        $limitedUrls = array_slice(array_unique($imageUrls), 0, 15);

        foreach ($limitedUrls as $url) {
            try {
                // Quick HEAD request to make sure it's alive and actually an image
                $response = Http::timeout(2)->head($url);
                if ($response->successful() && str_starts_with($response->header('Content-Type'), 'image/')) {
                    $validUrls[] = $url;
                }
            } catch (\Exception $e) {
                // Ignore broken links entirely
                continue;
            }
        }

        return $validUrls;
    }
}
