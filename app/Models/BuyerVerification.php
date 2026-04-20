<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BuyerVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'idin_status',
        'ideal_status',
        'kyc_status',
        'risk_score',
        'manual_review_required',
        'decision',
        'decision_reason',
        'reason_codes_json',
        'submitted_at',
        'approved_at',
        'verified_at',
        'expires_at',
        'reviewed_by',
        'latest_signhost_phase_id',
    ];

    protected $casts = [
        'reason_codes_json' => 'json',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'manual_review_required' => 'boolean',
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
        return $this->hasOne(BuyerProfile::class, 'user_id', 'user_id');
    }

    public function signhostTransactions(): HasMany
    {
        return $this->hasMany(BuyerVerificationSignhostTransaction::class);
    }

    public function kycAnswers(): HasMany
    {
        return $this->hasMany(BuyerVerificationKycAnswer::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(BuyerVerificationFlag::class);
    }

    public function latestSignhostPhase(): BelongsTo
    {
        return $this->belongsTo(BuyerVerificationSignhostTransaction::class, 'latest_signhost_phase_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, \App\Support\BuyerVerificationStatus::TERMINAL, true);
    }

    public function isCurrentlyValid(): bool
    {
        return strtolower((string) $this->decision) === 'approved'
            && $this->verified_at !== null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
