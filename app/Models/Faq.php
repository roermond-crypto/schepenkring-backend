<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faq extends Model
{
    use HasFactory;

    protected $table = 'faqs'; // Explicitly define the table name

    protected $fillable = [
        'location_id',
        'question',
        'answer',
        'category',
        'ai_score',
        'last_reviewed_at',
        'needs_update',
        'ai_review_summary',
        'ai_suggested_answer',
        'language',
        'department',
        'visibility',
        'brand',
        'model',
        'tags',
        'source_type',
        'deprecated_at',
        'superseded_by_faq_id',
        'last_indexed_at',
        'source_message_id',
        'trained_by_user_id',
    ];

    // Add default values if needed
    protected $attributes = [
        'category' => 'General',
        'visibility' => 'internal',
        'source_type' => 'faq',
        'views' => 0,
        'helpful' => 0,
        'not_helpful' => 0
    ];

    protected $casts = [
        'location_id' => 'integer',
        'tags' => 'array',
        'views' => 'integer',
        'helpful' => 'integer',
        'not_helpful' => 'integer',
        'ai_score' => 'integer',
        'needs_update' => 'boolean',
        'deprecated_at' => 'datetime',
        'last_indexed_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trained_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_faq_id');
    }

    public function knowledgeQuestions(): HasMany
    {
        return $this->hasMany(KnowledgeBrainQuestion::class, 'matched_faq_id');
    }

    public function knowledgeSuggestions(): HasMany
    {
        return $this->hasMany(KnowledgeBrainSuggestion::class, 'faq_id');
    }
}
