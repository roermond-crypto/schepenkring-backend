<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OwnerBid extends Model
{
    protected $fillable = [
        'yacht_id',
        'location_id',
        'user_id',
        'seller_id',
        'assigned_broker_id',
        'parent_bid_id',
        'type',
        'routing_mode',
        'amount',
        'message',
        'status',
        'expires_at',
        'ai_summary',
        'admin_notes',
        'paused_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paused_at' => 'datetime',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function assignedBroker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_broker_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OwnerBid::class, 'parent_bid_id');
    }

    public function deal(): HasOne
    {
        return $this->hasOne(Deal::class);
    }
}
