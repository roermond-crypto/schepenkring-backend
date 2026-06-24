<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferToken extends Model
{
    protected $fillable = [
        'offer_id',
        'token',
        'action',
        'counter_amount',
        'counter_message',
        'used',
        'used_at',
        'expires_at',
        'ip_address',
    ];

    protected $casts = [
        'used'           => 'boolean',
        'used_at'        => 'datetime',
        'expires_at'     => 'datetime',
        'counter_amount' => 'float',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->used && ! $this->isExpired();
    }
}
