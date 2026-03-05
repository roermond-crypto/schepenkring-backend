<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationEvent extends Model
{
    protected $fillable = [
        'conversation_id',
        'type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
