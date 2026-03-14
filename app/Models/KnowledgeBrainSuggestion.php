<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBrainSuggestion extends Model
{
    protected $fillable = [
        'location_id',
        'faq_id',
        'question_id',
        'approved_faq_id',
        'reviewed_by_user_id',
        'fingerprint',
        'type',
        'status',
        'title',
        'source_type',
        'question',
        'current_answer',
        'suggested_answer',
        'summary',
        'ai_score',
        'metadata',
        'first_detected_at',
        'last_detected_at',
        'approved_at',
        'declined_at',
        'reviewed_at',
    ];

    protected $casts = [
        'ai_score' => 'integer',
        'metadata' => 'array',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'approved_at' => 'datetime',
        'declined_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }

    public function questionLog(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBrainQuestion::class, 'question_id');
    }

    public function approvedFaq(): BelongsTo
    {
        return $this->belongsTo(Faq::class, 'approved_faq_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
