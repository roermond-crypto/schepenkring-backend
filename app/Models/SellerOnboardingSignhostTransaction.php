<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerOnboardingSignhostTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_onboarding_id',
        'user_id',
        'seller_onboarding_contract_id',
        'phase_type',
        'provider_step',
        'signhost_transaction_id',
        'status',
        'redirect_url',
        'payload_json',
        'provider_response_json',
        'webhook_last_payload',
        'completed_at',
    ];

    protected $casts = [
        'payload_json' => 'json',
        'provider_response_json' => 'json',
        'webhook_last_payload' => 'json',
        'completed_at' => 'datetime',
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
