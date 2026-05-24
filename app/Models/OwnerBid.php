<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OwnerBid extends Model
{
    protected $fillable = [
        'yacht_id',
        'user_id',
        'seller_id',
        'parent_bid_id',
        'type',
        'amount',
        'message',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
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
