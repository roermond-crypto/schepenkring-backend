<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InteractionTimelineEntry extends Model
{
    protected $fillable = [
        'user_id',
        'contact_id',
        'conversation_id',
        'event_type_id',
        'channel',
        'direction',
        'title',
        'body',
        'metadata',
        'template_id',
        'template_version',
        'source_type',
        'source_id',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function eventType()
    {
        return $this->belongsTo(InteractionEventType::class, 'event_type_id');
    }
}
