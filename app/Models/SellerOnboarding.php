<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerOnboarding extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'payment_status',
        'idin_status',
        'ideal_status',
        'kyc_status',
        'contract_status',
        'risk_score',
        'manual_review_required',
        'decision',
        'decision_reason',
        'can_publish_boat',
        'reason_codes_json',
        'submitted_at',
        'approved_at',
        'verified_at',
        'expires_at',
        'reviewed_by',
        'latest_contract_id',
        'latest_signhost_phase_id',
    ];

    protected $casts = [
        'reason_codes_json' => 'json',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'manual_review_required' => 'boolean',
        'can_publish_boat' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(SellerProfile::class, 'user_id', 'user_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(SellerOnboardingContract::class);
    }

    public function latestSignhostPhase(): BelongsTo
    {
        return $this->belongsTo(SellerOnboardingSignhostTransaction::class, 'latest_signhost_phase_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, \App\Support\SellerOnboardingStatus::TERMINAL, true);
    }

    public function isCurrentlyValid(): bool
    {
        return strtolower((string) $this->decision) === 'approved'
            && $this->verified_at !== null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function latestContract(): BelongsTo
    {
        return $this->belongsTo(SellerOnboardingContract::class, 'latest_contract_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SellerOnboardingPayment::class);
    }

    public function signhostTransactions(): HasMany
    {
        return $this->hasMany(SellerOnboardingSignhostTransaction::class);
    }

    public function kycAnswers(): HasMany
    {
        return $this->hasMany(SellerOnboardingKycAnswer::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(SellerOnboardingFlag::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SellerOnboardingReview::class);
    }
}
