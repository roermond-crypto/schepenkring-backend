<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationDeleteRequest extends Model
{
    protected $fillable = [
        'location_id',
        'requested_by',
        'approved_by',
        'move_to_location_id',
        'reason',
        'token',
        'status',
        'affected_records',
        'ip_address',
        'user_agent',
        'expires_at',
        'approved_at',
    ];

    protected $casts = [
        'affected_records' => 'array',
        'expires_at'       => 'datetime',
        'approved_at'      => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function moveToLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'move_to_location_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->isPending() && $this->expires_at->isPast();
    }
}
