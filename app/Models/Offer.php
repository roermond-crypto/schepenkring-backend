<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    protected $fillable = [
        'yacht_id',
        'location_id',
        'seller_id',
        'buyer_id',
        'lead_id',
        'conversation_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'amount',
        'asking_price',
        'minimum_amount',
        'status',
        'counter_amount',
        'counter_message',
        'parent_offer_id',
        'message',
        'below_minimum',
        'seller_notified',
        'seller_notified_at',
        'seller_responded_at',
        'source',
        'source_url',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'amount'             => 'float',
        'asking_price'       => 'float',
        'minimum_amount'     => 'float',
        'counter_amount'     => 'float',
        'below_minimum'      => 'boolean',
        'seller_notified'    => 'boolean',
        'seller_notified_at' => 'datetime',
        'seller_responded_at' => 'datetime',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(OfferToken::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_offer_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_offer_id');
    }

    public function isNew(): bool       { return $this->status === 'new'; }
    public function isPending(): bool   { return in_array($this->status, ['new', 'sent_to_seller']); }
    public function isAccepted(): bool  { return $this->status === 'seller_accepted'; }
    public function isRejected(): bool  { return $this->status === 'seller_rejected'; }
    public function isCountered(): bool { return $this->status === 'seller_countered'; }

    public function displayName(): string
    {
        return $this->buyer_name ?? $this->buyer?->name ?? 'Onbekend';
    }

    public function formattedAmount(): string
    {
        return '€ ' . number_format((float) $this->amount, 0, ',', '.');
    }
}
