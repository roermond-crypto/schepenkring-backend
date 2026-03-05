<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'text',
        'language',
        'channel',
        'external_message_id',
        'message_type',
        'status',
        'ai_confidence',
        'metadata',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'ai_confidence' => 'decimal:2',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'message_id');
    }
}
