<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

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
        return $this->belongsTo(Location::class, 'location_id');
    }
}
