<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bidder extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'address',
        'postal_code',
        'city',
        'phone',
        'email',
        'verified_at',
        'verification_token_hash',
        'verification_expires_at',
        'verification_sent_at',
        'verification_ip',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'verification_expires_at' => 'datetime',
        'verification_sent_at' => 'datetime',
    ];

    protected $hidden = [
        'verification_token_hash',
        'verification_expires_at',
        'verification_ip',
    ];

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BidSession::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
