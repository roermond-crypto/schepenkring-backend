<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuctionSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'yacht_id',
        'location_id',
        'highest_bid',
        'highest_bidder_id',
        'started_by',
        'ended_by',
        'start_time',
        'end_time',
        'status',
        'last_bid_at',
        'extension_count',
        'total_bids',
        'unique_bidders',
    ];

    protected $casts = [
        'highest_bid' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'last_bid_at' => 'datetime',
        'extension_count' => 'integer',
        'total_bids' => 'integer',
        'unique_bidders' => 'integer',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function highestBidder(): BelongsTo
    {
        return $this->belongsTo(Bidder::class, 'highest_bidder_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }
}
