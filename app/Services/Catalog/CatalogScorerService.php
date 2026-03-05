<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;

class CatalogScorerService
{
    /**
     * Calculates a 0-100 quality score for a normalized boat in the catalog.
     * High scoring boats will be indexed into Pinecone for RAG.
     */
    public function calculateScore(object $boatRecord): int
    {
        $score = 0;

        // 1. Core Brand/Model mappings (+50 max)
        if (!empty($boatRecord->brand_id)) {
            $score += 25; // Canonical match
        }
        if (!empty($boatRecord->model_id)) {
            $score += 25; // Canonical match
        }

        // 2. Valid Year (+15)
        if (!empty($boatRecord->year) && $boatRecord->year > 1900 && $boatRecord->year <= ((int)date('Y') + 1)) {
            $score += 15;
        }

        // 3. Valid Length (+15)
        if (!empty($boatRecord->length) && $boatRecord->length >= 1 && $boatRecord->length <= 150) {
            $score += 15;
        }

        // 4. Description Quality (+10)
        if (!empty($boatRecord->description) && strlen($boatRecord->description) >= 120) {
            $score += 10;
        }

        // 5. Image Quantities (+10) & Penalties (-10)
        $images = json_decode($boatRecord->image_urls, true) ?? [];
        $imgCount = count($images);
        
        if ($imgCount >= 6) {
            $score += 10;
        } elseif ($imgCount < 3) {
            $score -= 10; // Penalty for lack of visual context
        }

        // 6. Impossible Dimensions Penalty (-20)
        if (
            (!empty($boatRecord->year) && $boatRecord->year == 0) || 
            (!empty($boatRecord->length) && $boatRecord->length == 0)
        ) {
            $score -= 20;
        }

        // Clamp between 0 and 100
        return max(0, min(100, $score));
    }

    /**
     * Updates the score on a specific row in the catalog.
     */
    public function updateScoreForCatalogId(int $catalogId): void
    {
        $boat = DB::table('boat_catalog')->where('id', $catalogId)->first();
        if (!$boat) return;

        $newScore = $this->calculateScore($boat);

        DB::table('boat_catalog')->where('id', $catalogId)->update([
            'quality_score' => $newScore
        ]);
    }
}
