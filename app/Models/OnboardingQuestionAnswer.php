<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingQuestionAnswer extends Model
{
    protected $fillable = [
        'user_id',
        'onboarding_question_id',
        'answer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(OnboardingQuestion::class, 'onboarding_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
