<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id',
        'impersonator_id',
        'action',
        'risk_level',
        'result',
        'location_id',
        'target_type',
        'target_id',
        'entity_type',
        'entity_id',
        'meta',
        'snapshot_before',
        'snapshot_after',
        'ip_address',
        'ip_hash',
        'user_agent',
        'device_id',
        'request_id',
        'idempotency_key',
    ];

    protected $casts = [
        'meta'            => 'array',
        'snapshot_before' => 'array',
        'snapshot_after'  => 'array',
    ];

    // ── Relationships ────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeForModel($query, string $type, int $id)
    {
        return $query->where('auditable_type', $type)->where('auditable_id', $id);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
