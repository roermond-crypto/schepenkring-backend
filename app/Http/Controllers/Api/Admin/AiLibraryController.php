<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrapeRun;
use App\Models\Yacht;
use App\Services\BoatImportValidationService;
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
        $totalYachts = Yacht::query()
            ->where('source', 'schepenkring_sold_archive')
            ->count();
        $withBrand = Yacht::query()
            ->where('source', 'schepenkring_sold_archive')
            ->whereNotNull('manufacturer')
            ->count();
        $withModel = Yacht::query()
            ->where('source', 'schepenkring_sold_archive')
            ->whereNotNull('model')
            ->count();
        $withYear = Yacht::query()
            ->where('source', 'schepenkring_sold_archive')
            ->whereNotNull('year')
            ->count();
        $withLength = Yacht::query()
            ->where('source', 'schepenkring_sold_archive')
            ->whereHas('dimensions', fn ($query) => $query->whereNotNull('loa'))
            ->count();
        $lastRun = ScrapeRun::query()
            ->where('source', 'schepenkring_sold_archive')
            ->latest('started_at')
            ->first();

        return response()->json([
            'total_yachts' => $totalYachts,
            'fields_coverage' => [
                'brand' => $totalYachts > 0 ? round($withBrand / $totalYachts, 2) : 0,
                'model' => $totalYachts > 0 ? round($withModel / $totalYachts, 2) : 0,
                'year' => $totalYachts > 0 ? round($withYear / $totalYachts, 2) : 0,
                'length' => $totalYachts > 0 ? round($withLength / $totalYachts, 2) : 0,
            ],
            'last_scrape_at' => Yacht::max('created_at'),
            'last_scrape_run' => $lastRun ? [
                'id' => $lastRun->id,
                'status' => $lastRun->status,
                'boats_seen' => $lastRun->boats_seen,
                'expected_total' => $lastRun->expected_total,
                'completeness_ratio' => $lastRun->completeness_ratio,
                'finished_at' => optional($lastRun->finished_at)->toISOString(),
            ] : null,
        ]);
    }

    public function reIndex(Request $request): JsonResponse
    {
        $limit = $request->query('limit') !== null ? (int) $request->query('limit') : null;
        $minimumCompleteness = (float) $request->query('min_completeness', 0.98);
        $force = $request->boolean('force');
        $deleteExisting = $request->boolean('delete_existing');
        $lastRun = ScrapeRun::query()
            ->where('source', 'schepenkring_sold_archive')
            ->latest('started_at')
            ->first();

        if (! $force && ! ($lastRun?->passedCompletenessGate($minimumCompleteness) ?? false)) {
            return response()->json([
                'message' => 'Latest scrape run has not passed the completeness gate.',
                'required_completeness' => $minimumCompleteness,
                'last_scrape_run' => $lastRun,
            ], 409);
        }

        if ($deleteExisting && ! $this->pinecone->deleteAllYachtVectors()) {
            return response()->json([
                'message' => 'Could not delete old Pinecone vectors. Re-index aborted.',
            ], 502);
        }

        $yachts = Yacht::where('source', 'schepenkring_sold_archive')
            ->whereNotNull('manufacturer')
            ->whereNotNull('model')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
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
            'deleted_existing' => $deleteExisting,
            'scrape_run_id' => $lastRun?->id,
        ]);
    }

    public function qaComparison(Request $request, BoatImportValidationService $validator): JsonResponse
    {
        $limit = min((int) $request->query('limit', 50), 250);
        $yachts = Yacht::query()
            ->where('source', 'schepenkring_sold_archive')
            ->with(['dimensions', 'accommodation'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        $invalid = [];

        foreach ($yachts as $yacht) {
            $result = $validator->validate([
                'manufacturer' => $yacht->manufacturer,
                'model' => $yacht->model,
                'boat_name' => $yacht->boat_name,
                'year' => $yacht->year,
                'loa' => $yacht->dimensions?->loa,
                'beam' => $yacht->dimensions?->beam,
                'draft' => $yacht->dimensions?->draft,
                'location' => $yacht->vessel_lying,
                'description' => $yacht->short_description_nl,
                'cabins' => $yacht->accommodation?->cabins,
                'berths' => $yacht->accommodation?->berths,
            ]);

            if (! $result['valid']) {
                $invalid[] = [
                    'yacht_id' => $yacht->id,
                    'boat_name' => $yacht->boat_name,
                    'external_url' => $yacht->external_url,
                    'issues' => $result['issues'],
                ];
            }
        }

        return response()->json([
            'sampled' => $yachts->count(),
            'invalid_count' => count($invalid),
            'valid_count' => $yachts->count() - count($invalid),
            'invalid' => $invalid,
        ]);
    }
}
