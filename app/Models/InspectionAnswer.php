<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionAnswer extends Model
{
    protected $fillable = [
        'inspection_id',
        'question_id',
        'ai_answer',
        'ai_confidence',
        'ai_evidence',
        'human_answer',
        'review_status',
    ];

    protected $casts = [
        'ai_confidence' => 'float',
        'ai_evidence' => 'array',
        'review_status' => 'string',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(BoatInspection::class, 'inspection_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(BoatCheck::class, 'question_id');
    }
}