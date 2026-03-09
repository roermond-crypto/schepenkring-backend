<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'action',
        'risk_level',
        'result',
        'actor_id',
        'impersonator_id',
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
        'meta' => 'array',
        'snapshot_before' => 'array',
        'snapshot_after' => 'array',
    ];

    // ── Relationships ────────────────────────────────────

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->actor();
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'target_type', 'target_id');
    }

    public function auditable(): MorphTo
    {
        return $this->entity();
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeForModel($query, string $type, int $id)
    {
        return $query->where(function ($builder) use ($type, $id) {
            $builder->where(function ($inner) use ($type, $id) {
                $inner->where('entity_type', $type)
                    ->where('entity_id', $id);
            })->orWhere(function ($inner) use ($type, $id) {
                $inner->where('target_type', $type)
                    ->where('target_id', $id);
            });
        });
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function getMetadataAttribute(): ?array
    {
        return $this->meta;
    }

    public function getOldValuesAttribute(): ?array
    {
        return $this->snapshot_before;
    }

    public function getNewValuesAttribute(): ?array
    {
        return $this->snapshot_after;
    }

    public function getUserIdAttribute(): ?int
    {
        return $this->actor_id;
    }

    public function getAuditableTypeAttribute(): ?string
    {
        return $this->entity_type ?? $this->target_type;
    }

    public function getAuditableIdAttribute(): ?int
    {
        return $this->entity_id ?? $this->target_id;
    }
}
