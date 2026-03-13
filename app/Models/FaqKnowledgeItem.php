<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqKnowledgeItem extends Model
{
    protected $fillable = [
        'document_id',
        'location_id',
        'approved_faq_id',
        'reviewed_by_user_id',
        'chunk_index',
        'status',
        'source_type',
        'language',
        'category',
        'department',
        'visibility',
        'brand',
        'model',
        'tags',
        'question',
        'answer',
        'source_excerpt',
        'review_notes',
        'metadata',
        'approved_at',
        'declined_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(FaqKnowledgeDocument::class, 'document_id');
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
