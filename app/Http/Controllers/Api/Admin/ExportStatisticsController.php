<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Services\PlatformHealthService;
use Illuminate\Http\JsonResponse;

/**
 * Fleet-wide export statistics for the Integration Center's overview —
 * assembled entirely from PlatformHealthService::forPlatform() (already
 * real, built earlier for the Platform Configuration Statistics tab), one
 * call per active platform. Queue/running-exports/retry-queue are
 * reported as not_tracked rather than fabricated: no platform in this
 * system has real async export job dispatch (BoatPlatformPublicationController
 * ::sync() only flips a status flag, YachtShift's HTTP push is synchronous),
 * so there is no real queue depth to show.
 */
class ExportStatisticsController extends Controller
{
    public function __construct(
        private readonly PlatformHealthService $health,
    ) {
    }

    public function index(): JsonResponse
    {
        $platforms = Platform::where('is_active', true)->orderBy('priority')->get();

        $perPlatform = $platforms->map(function (Platform $platform) {
            return array_merge(
                ['platform_id' => $platform->id, 'platform_name' => $platform->name],
                $this->health->forPlatform($platform)
            );
        });

        $lastExportTimes = $perPlatform->pluck('last_successful_export')->filter();
        $lastSyncTimes = $perPlatform->pluck('last_sync')->filter();
        $successRates30d = $perPlatform->pluck('success_rate_30d')->filter();

        return response()->json([
            'platforms' => $perPlatform,
            'overview' => [
                'last_export' => $lastExportTimes->max(),
                'last_synchronization' => $lastSyncTimes->max(),
                'total_exported_yachts' => $perPlatform->sum('total_exported_yachts'),
                'waiting_exports' => $perPlatform->sum('waiting_exports'),
                'failed_exports' => $perPlatform->sum('failed_exports'),
                'success_rate_30d' => $successRates30d->isEmpty() ? null : round($successRates30d->avg(), 1),
                'running_exports' => null,
                'queue_size' => null,
                'retry_queue_size' => null,
                'not_tracked' => ['running_exports', 'queue_size', 'retry_queue_size', 'avg_export_duration_ms'],
            ],
        ]);
    }
}
