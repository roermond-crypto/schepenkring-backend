<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBrainQuestion extends Model
{
    protected $fillable = [
        'location_id',
        'matched_faq_id',
        'source_type',
        'status',
        'normalized_question',
        'question',
        'times_asked',
        'confidence',
        'metadata',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'confidence' => 'decimal:4',
        'metadata' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function matchedFaq(): BelongsTo
    {
        return $this->belongsTo(Faq::class, 'matched_faq_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(KnowledgeBrainSuggestion::class, 'question_id');
    }
}
