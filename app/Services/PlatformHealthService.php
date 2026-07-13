<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BoatPlatformPublication;
use App\Models\Platform;
use App\Models\Yacht;

/**
 * Computes a normalized health snapshot for a platform from real sync data,
 * since no platform in this system exposes a live ping/health endpoint.
 *
 * YachtShift is not a generic marketplace publication target — it's the
 * directional sync engine behind `yachts.yachtshift_*` columns and its own
 * AuditLog trail — so it gets a dedicated branch. Every other platform is
 * scored from its `boat_platform_publications` rows. Both branches return
 * the same shape so the Statistics tab can render generically.
 */
class PlatformHealthService
{
    private const SUCCESS_STATUSES = ['published', 'synced'];

    public function forPlatform(Platform $platform): array
    {
        return $platform->slug === 'yachtshift'
            ? $this->yachtShiftHealth()
            : $this->marketplaceHealth($platform);
    }

    private function marketplaceHealth(Platform $platform): array
    {
        $base = fn () => BoatPlatformPublication::where('platform_id', $platform->id);

        $lastErrorRow = $base()->whereNotNull('last_error_at')
            ->orderByDesc('last_error_at')
            ->first();

        return [
            'source' => 'boat_platform_publications',
            'last_successful_export' => $base()->max('last_success_at'),
            'last_sync' => $base()->max('last_sync_at'),
            'last_successful_api_call' => $base()->max('last_success_at'),
            'last_failed_sync' => $base()->max('last_error_at'),
            'success_rate_7d' => $this->publicationSuccessRate($platform->id, 7),
            'success_rate_30d' => $this->publicationSuccessRate($platform->id, 30),
            'total_exported_yachts' => $base()->whereIn('status', self::SUCCESS_STATUSES)->count(),
            'waiting_exports' => $base()->where('status', 'pending')->count(),
            'failed_exports' => $base()->where('status', 'failed')->count(),
            'last_error' => $lastErrorRow?->last_error_message,
            'avg_export_duration_ms' => null,
        ];
    }

    private function publicationSuccessRate(int $platformId, int $days): ?float
    {
        $since = now()->subDays($days);

        $total = BoatPlatformPublication::where('platform_id', $platformId)
            ->where('last_sync_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return null;
        }

        $success = BoatPlatformPublication::where('platform_id', $platformId)
            ->where('last_sync_at', '>=', $since)
            ->whereIn('status', self::SUCCESS_STATUSES)
            ->count();

        return round(($success / $total) * 100, 1);
    }

    private function yachtShiftHealth(): array
    {
        $totalYachts = Yacht::count();
        $synced = Yacht::whereNotNull('yachtshift_synced_at')->count();

        $lastErrorRow = Yacht::whereNotNull('yachtshift_last_export_error')
            ->orderByDesc('yachtshift_last_exported_at')
            ->first();

        $lastFailedLog = AuditLog::whereIn('action', ['yachtshift.export.failed', 'yachtshift.import.failed'])
            ->latest('created_at')
            ->first();

        return [
            'source' => 'yachtshift',
            'last_successful_export' => Yacht::whereNotNull('yachtshift_last_exported_at')->max('yachtshift_last_exported_at'),
            'last_sync' => Yacht::whereNotNull('yachtshift_synced_at')->max('yachtshift_synced_at'),
            'last_successful_api_call' => Yacht::whereNotNull('yachtshift_synced_at')->max('yachtshift_synced_at'),
            'last_failed_sync' => $lastFailedLog?->created_at,
            'success_rate_7d' => $this->auditLogSuccessRate(7),
            'success_rate_30d' => $this->auditLogSuccessRate(30),
            'total_exported_yachts' => $synced,
            'waiting_exports' => max($totalYachts - $synced, 0),
            'failed_exports' => Yacht::where('yachtshift_publish_status', 'failed')->count(),
            'last_error' => $lastErrorRow?->yachtshift_last_export_error,
            'avg_export_duration_ms' => null,
        ];
    }

    private function auditLogSuccessRate(int $days): ?float
    {
        $since = now()->subDays($days);
        $successActions = ['yachtshift.export.completed', 'yachtshift.import.completed'];
        $allOutcomeActions = [
            'yachtshift.export.completed',
            'yachtshift.export.completed_with_errors',
            'yachtshift.export.failed',
            'yachtshift.import.completed',
            'yachtshift.import.failed',
        ];

        $total = AuditLog::whereIn('action', $allOutcomeActions)
            ->where('created_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return null;
        }

        $success = AuditLog::whereIn('action', $successActions)
            ->where('created_at', '>=', $since)
            ->count();

        return round(($success / $total) * 100, 1);
    }
}
