<?php

namespace App\Traits;

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
        ?string $reason = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id'        => Auth::id(),
            'action'         => $action,
            'auditable_type' => static::class,
            'auditable_id'   => $this->getKey(),
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
            'reason'         => $reason,
            'metadata'       => $metadata,
        ]);
    }

    /**
     * Get all audit logs for this model instance.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
