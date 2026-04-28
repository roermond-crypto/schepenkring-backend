<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerVerificationKycAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_verification_id',
        'kyc_question_id',
        'kyc_question_option_id',
        'question_key',
        'answer_value',
        'normalized_value',
        'answer_payload',
        'submitted_at',
    ];

    protected $casts = [
        'answer_payload' => 'json',
        'submitted_at' => 'datetime',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(BuyerVerification::class, 'buyer_verification_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(KycQuestion::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(KycQuestionOption::class, 'kyc_question_option_id');
    }
}
