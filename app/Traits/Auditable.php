<?php

namespace App\Traits;

use App\Enums\AuditResult;
use App\Enums\RiskLevel;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Trait Auditable
 *
 * Add to any model that should log changes to the audit_logs table.
 *
 * Usage:
 *   use App\Traits\Auditable;
 *   class Yacht extends Model { use Auditable; }
 *
 * Then changes are automatically logged on created/updated/deleted.
 * Manual logging: $yacht->logAudit('ai_applied', $oldValues, $newValues, ['prompt_hash' => '...']);
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->logAudit('created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) return;

            $old = collect($dirty)->mapWithKeys(fn($v, $k) => [$k => $model->getOriginal($k)])->toArray();
            $model->logAudit('updated', $old, $dirty);
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted', $model->getOriginal(), null);
        });
    }

    /**
     * Write an audit log entry.
     */
    public function logAudit(
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        RiskLevel|string|null $riskLevel = null,
        ?string $reason = null,
    ): AuditLog {
        $ipAddress = Request::ip();
        $resolvedRiskLevel = $riskLevel instanceof RiskLevel
            ? $riskLevel->value
            : (is_string($riskLevel) && $riskLevel !== ''
                ? strtoupper($riskLevel)
                : RiskLevel::LOW->value);

        return AuditLog::create([
            'action' => $action,
            'risk_level' => $resolvedRiskLevel,
            'result' => AuditResult::SUCCESS->value,
            'actor_id' => Auth::id(),
            'location_id' => $this->location_id ?? $this->client_location_id ?? Auth::user()?->client_location_id,
            'target_type' => static::class,
            'target_id' => $this->getKey(),
            'entity_type' => static::class,
            'entity_id' => $this->getKey(),
            'meta' => array_filter([
                'reason' => $reason,
                'metadata' => $metadata,
                'path' => Request::path(),
                'method' => Request::method(),
            ], static fn ($value) => $value !== null),
            'snapshot_before' => $oldValues,
            'snapshot_after' => $newValues,
            'ip_address' => $ipAddress,
            'ip_hash' => $ipAddress ? hash('sha256', $ipAddress) : null,
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Get all audit logs for this model instance.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'entity');
    }
}
