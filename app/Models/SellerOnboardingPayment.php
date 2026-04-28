<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerOnboardingPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_onboarding_id',
        'user_id',
        'type',
        'mollie_payment_id',
        'idempotency_key',
        'amount_currency',
        'amount_value',
        'status',
        'checkout_url',
        'webhook_events_count',
        'metadata_json',
        'paid_at',
    ];

    protected $casts = [
        'metadata_json' => 'json',
        'paid_at' => 'datetime',
        'amount_value' => 'decimal:2',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(SellerOnboarding::class, 'seller_onboarding_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
