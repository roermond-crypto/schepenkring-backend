<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MappingRegressionRun;
use App\Models\OpenMarineFieldMappingVersion;
use App\Models\Yacht;
use App\Services\OpenMarineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Validates a sample of real yachts against the current OpenMarine mapping
 * in one pass — "did this mapping change break anything." Reuses
 * OpenMarineService::validate() as-is (already a clean, side-effect-free
 * per-yacht check); this controller is purely the loop + persistence.
 */
class MappingRegressionController extends Controller
{
    public function __construct(
        private readonly OpenMarineService $openMarine,
    ) {
    }

    public function run(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 250), 1000);

        $yachts = Yacht::query()
            ->whereIn('status', OpenMarineService::EXPORTABLE_STATUSES)
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        $run = MappingRegressionRun::create([
            'mapping_version' => OpenMarineFieldMappingVersion::max('version'),
            'total_yachts' => $yachts->count(),
            'passed_count' => 0,
            'failed_count' => 0,
            'triggered_by_id' => $request->user()?->id,
        ]);

        $passed = 0;
        $failed = 0;

        foreach ($yachts as $yacht) {
            [$errors, $warnings] = $this->openMarine->validate($yacht);
            $ok = empty($errors);
            $ok ? $passed++ : $failed++;

            $run->results()->create([
                'yacht_id' => $yacht->id,
                'passed' => $ok,
                'errors' => $errors,
                'warnings' => $warnings,
            ]);
        }

        $run->update(['passed_count' => $passed, 'failed_count' => $failed]);

        return response()->json($run->fresh());
    }

    public function index(): JsonResponse
    {
        $runs = MappingRegressionRun::with('triggeredBy:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $runs]);
    }

    public function show(MappingRegressionRun $run): JsonResponse
    {
        $run->load(['results.yacht:id,boat_name']);

        return response()->json($run);
    }
}
