<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerOnboardingReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_onboarding_id',
        'reviewer_id',
        'status',
        'outcome',
        'notes',
        'opened_at',
        'resolved_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function onboarding()
    {
        return $this->belongsTo(SellerOnboarding::class, 'seller_onboarding_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
