<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerOnboardingContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_onboarding_id',
        'user_id',
        'contract_uid',
        'contract_type',
        'template_version',
        'contract_pdf_path',
        'contract_sha256',
        'signed_document_path',
        'signhost_transaction_id',
        'sign_url',
        'status',
        'contract_payload',
        'generated_at',
        'signed_at',
    ];

    protected $casts = [
        'contract_payload' => 'json',
        'generated_at' => 'datetime',
        'signed_at' => 'datetime',
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
