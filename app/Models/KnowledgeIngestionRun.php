<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeIngestionRun extends Model
{
    protected $fillable = [
        'source_type',
        'source_table',
        'source_reference',
        'location_id',
        'triggered_by_user_id',
        'status',
        'documents_count',
        'chunks_count',
        'embeddings_count',
        'entities_count',
        'failures_count',
        'metadata',
        'error_text',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'location_id' => 'integer',
        'triggered_by_user_id' => 'integer',
        'documents_count' => 'integer',
        'chunks_count' => 'integer',
        'embeddings_count' => 'integer',
        'entities_count' => 'integer',
        'failures_count' => 'integer',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
