<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeEntity extends Model
{
    protected $fillable = [
        'type',
        'source_table',
        'source_id',
        'location_id',
        'title',
        'summary',
        'language',
        'status',
        'metadata',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'location_id' => 'integer',
        'metadata' => 'array',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(KnowledgeRelationship::class, 'from_entity_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(KnowledgeRelationship::class, 'to_entity_id');
    }
}
