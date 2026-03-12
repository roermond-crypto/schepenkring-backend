<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaqKnowledgeDocument extends Model
{
    protected $fillable = [
        'location_id',
        'uploaded_by_user_id',
        'file_name',
        'file_path',
        'mime_type',
        'extension',
        'source_type',
        'status',
        'language',
        'category',
        'department',
        'visibility',
        'brand',
        'model',
        'tags',
        'chunk_count',
        'generated_qna_count',
        'extracted_text',
        'processing_error',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FaqKnowledgeItem::class, 'document_id');
    }
}
