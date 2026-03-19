<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeRelationship extends Model
{
    protected $fillable = [
        'from_entity_id',
        'to_entity_id',
        'relationship_type',
        'weight',
        'metadata',
    ];

    protected $casts = [
        'from_entity_id' => 'integer',
        'to_entity_id' => 'integer',
        'weight' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function fromEntity(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntity::class, 'from_entity_id');
    }

    public function toEntity(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntity::class, 'to_entity_id');
    }
}
