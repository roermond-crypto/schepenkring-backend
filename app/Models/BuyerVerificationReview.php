<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerVerificationReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_verification_id',
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

    public function verification()
    {
        return $this->belongsTo(BuyerVerification::class, 'buyer_verification_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
