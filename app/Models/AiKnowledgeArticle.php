<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiKnowledgeArticle extends Model
{
    protected $fillable = [
        'title',
        'content',
        'match_type',
        'match_value',
        'tags',
        'language',
        'status',
        'pinecone_id',
        'last_embedded_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'last_embedded_at' => 'datetime',
        'created_by_user_id' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Build the text that will be embedded in Pinecone.
     * Combines title, match context, and content for semantic search.
     */
    public function buildEmbeddingText(): string
    {
        $parts = [];

        $parts[] = $this->title;

        if ($this->match_type && $this->match_value) {
            $parts[] = ucfirst($this->match_type) . ': ' . $this->match_value;
        }

        if (is_array($this->tags) && !empty($this->tags)) {
            $parts[] = 'Tags: ' . implode(', ', $this->tags);
        }

        $plainContent = trim(strip_tags($this->content ?? ''));
        if ($plainContent !== '') {
            $parts[] = $plainContent;
        }

        return implode('. ', array_filter($parts));
    }
}
