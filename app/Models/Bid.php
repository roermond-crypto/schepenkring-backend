<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'yacht_id',
        'auction_session_id',
        'bidder_id',
        'location_id',
        'amount',
        'status',
        'bidder_name',
        'bidder_email',
        'bidder_phone',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $hidden = [
        'ip_address',
        'user_agent',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function auctionSession(): BelongsTo
    {
        return $this->belongsTo(AuctionSession::class);
    }

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(Bidder::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
