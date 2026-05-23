<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    protected $fillable = [
        'owner_bid_id',
        'yacht_id',
        'buyer_id',
        'seller_id',
        'conversation_id',
        'agreed_amount',
        'status',
    ];

    protected $casts = [
        'agreed_amount' => 'decimal:2',
    ];

    public function ownerBid(): BelongsTo
    {
        return $this->belongsTo(OwnerBid::class);
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
