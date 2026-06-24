<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycAnswer extends Model
{
    protected $table = 'kyc_answers';

    protected $fillable = [
        'kyc_case_id', 'question_template_id',
        'answer', 'note', 'risk_points_awarded',
        'answered_by', 'answered_at',
    ];

    protected $casts = [
        'risk_points_awarded' => 'integer',
        'answered_at'         => 'datetime',
    ];

    public function kycCase(): BelongsTo    { return $this->belongsTo(KycCase::class); }
    public function question(): BelongsTo   { return $this->belongsTo(KycQuestionTemplate::class, 'question_template_id'); }
    public function answeredBy(): BelongsTo { return $this->belongsTo(User::class, 'answered_by'); }
}
