<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatPlatformPublication;
use App\Models\Platform;
use App\Models\SignRequest;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublishingHealthController extends Controller
{
    /**
     * GET /admin/publishing/health
     *
     * Returns a snapshot of the publishing pipeline health for the dashboard widget.
     */
    public function health(): JsonResponse
    {
        // Publishable = approved or published and not soft-deleted
        $publishableStatuses = ['approved', 'published', 'active'];

        $publishable = Yacht::whereIn('status', $publishableStatuses)->count();

        // Boats that have at least one enabled, synced publication
        $exported = Yacht::whereIn('status', $publishableStatuses)
            ->whereHas('platformPublications', fn ($q) => $q->where('enabled', true)->where('status', 'synced'))
            ->count();

        // Publications with failed status
        $failedExports = BoatPlatformPublication::where('status', 'failed')->count();

        // Active platforms with at least one failed publication
        $platformsWithErrors = Platform::where('is_active', true)
            ->whereHas('boatPublications', fn ($q) => $q->where('status', 'failed'))
            ->count();

        // Boats that should NOT be exported but appear in publications as enabled+synced
        $draftInExport = Yacht::whereNotIn('status', $publishableStatuses)
            ->whereHas('platformPublications', fn ($q) => $q->where('enabled', true)->where('status', 'synced'))
            ->count();

        // Pending Signhost contracts
        $signclosedStatuses   = ['SIGNED', 'COMPLETED', 'CANCELLED', 'EXPIRED'];
        $signclosePending = SignRequest::whereNotIn('status', $signclosedStatuses)->count();

        // Last successful sync timestamp
        $lastSuccess = BoatPlatformPublication::whereNotNull('last_success_at')
            ->max('last_success_at');

        // Overall health
        $status = 'healthy';
        if ($failedExports > 0 || $platformsWithErrors > 0) {
            $status = 'warning';
        }
        if ($draftInExport > 0 || ($failedExports > 5)) {
            $status = 'critical';
        }

        return response()->json([
            'status'              => $status,
            'publishable_boats'   => $publishable,
            'exported_boats'      => $exported,
            'failed_exports'      => $failedExports,
            'platforms_with_errors' => $platformsWithErrors,
            'draft_boats_in_export' => $draftInExport,
            'signhost_pending'    => $signclosePending,
            'broken_images'       => 0, // placeholder — would require URL checking job
            'last_feed_at'        => $lastSuccess,
        ]);
    }
}
