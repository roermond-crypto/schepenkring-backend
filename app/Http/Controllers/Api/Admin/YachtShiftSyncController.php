<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Services\YachtCompletenessService;
use App\Services\YachtShiftSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YachtShiftSyncController extends Controller
{
    public function __construct(
        private YachtShiftSyncService $sync,
        private YachtCompletenessService $completeness,
    ) {
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
                $result['import'] = $this->sync->import($dryRun, $request->user());
            }
            if ($direction === 'export' || $direction === 'both') {
                $result['export'] = $this->sync->export($dryRun, $request->user());
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
            'publish_statuses' => Yacht::query()
                ->selectRaw('COALESCE(yachtshift_publish_status, ?) as status, COUNT(*) as aggregate', ['draft'])
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'failed_exports' => Yacht::where('yachtshift_publish_status', 'failed')->count(),
            'pending_conflicts' => \App\Models\YachtshiftSyncConflict::where('status', 'pending')->count(),
        ]);
    }

    public function publish(Request $request, Yacht $yacht): JsonResponse
    {
        $validated = $request->validate([
            'dry_run' => 'sometimes|boolean',
            'force' => 'sometimes|boolean',
        ]);

        $missing = $this->completeness->missingRequired($yacht);
        if (! empty($missing) && ! ($validated['force'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Required fields are missing. Pass force=true to publish anyway.',
                'missing_required' => $missing,
            ], 422);
        }

        $result = $this->sync->publishYacht($yacht, $validated['dry_run'] ?? false, $request->user());

        return response()->json($result, ($result['success'] ?? false) ? 200 : 502);
    }

    public function retryExport(Request $request, Yacht $yacht): JsonResponse
    {
        $validated = $request->validate([
            'dry_run' => 'sometimes|boolean',
        ]);

        $result = $this->sync->retryExport($yacht, $validated['dry_run'] ?? false, $request->user());

        return response()->json($result, ($result['success'] ?? false) ? 200 : 502);
    }

    public function resolveConflict(Request $request, int $conflictId): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => 'required|in:local,remote,custom',
            'value' => 'required_if:resolution,custom|nullable',
        ]);

        try {
            $result = $this->sync->resolveConflict(
                $conflictId,
                $validated['resolution'],
                $request->user(),
                $validated['value'] ?? null
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
