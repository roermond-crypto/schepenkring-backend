<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Services\PineconeMatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiLibraryController extends Controller
{
    public function __construct(private PineconeMatcherService $pinecone)
    {
    }

    public function stats(): JsonResponse
    {
        $totalYachts = Yacht::count();
        $withBrand = Yacht::whereNotNull('brand')->count();
        $withModel = Yacht::whereNotNull('model')->count();
        $withYear = Yacht::whereNotNull('year')->count();
        $withLength = Yacht::whereNotNull('length')->count();

        return response()->json([
            'total_boats_scraped' => $totalYachts,
            'fields_coverage' => [
                'brand' => $totalYachts > 0 ? round($withBrand / $totalYachts, 2) : 0,
                'model' => $totalYachts > 0 ? round($withModel / $totalYachts, 2) : 0,
                'year' => $totalYachts > 0 ? round($withYear / $totalYachts, 2) : 0,
                'length' => $totalYachts > 0 ? round($withLength / $totalYachts, 2) : 0,
            ],
            'last_scrape_at' => Yacht::max('created_at'),
        ]);
    }

    public function reIndex(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 100);

        $yachts = Yacht::whereNotNull('brand')
            ->whereNotNull('model')
            ->limit($limit)
            ->get();

        $indexed = 0;
        $failed = 0;

        foreach ($yachts as $yacht) {
            try {
                $this->pinecone->upsertYacht($yacht);
                $indexed++;
            } catch (\Throwable $e) {
                Log::warning('AiLibraryController: re-index failed for yacht', [
                    'yacht_id' => $yacht->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return response()->json([
            'indexed' => $indexed,
            'failed' => $failed,
            'total_attempted' => $yachts->count(),
        ]);
    }
}
