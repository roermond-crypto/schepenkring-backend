<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Services\YachtShiftSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YachtShiftSyncController extends Controller
{
    public function __construct(private YachtShiftSyncService $sync)
    {
    }

    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction' => 'required|in:import,export,both',
            'dry_run' => 'sometimes|boolean',
        ]);

        $dryRun = $validated['dry_run'] ?? false;
        $direction = $validated['direction'];
        $result = ['direction' => $direction, 'dry_run' => $dryRun];

        try {
            if ($direction === 'import' || $direction === 'both') {
                $result['import'] = $this->sync->import($dryRun);
            }
            if ($direction === 'export' || $direction === 'both') {
                $result['export'] = $this->sync->export($dryRun);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($result);
    }

    public function status(): JsonResponse
    {
        $totalYachts = Yacht::count();
        $synced = Yacht::whereNotNull('yachtshift_synced_at')->count();
        $lastSync = Yacht::whereNotNull('yachtshift_synced_at')->max('yachtshift_synced_at');

        return response()->json([
            'total_yachts' => $totalYachts,
            'synced_to_yachtshift' => $synced,
            'pending_export' => $totalYachts - $synced,
            'last_sync_at' => $lastSync,
        ]);
    }
}
