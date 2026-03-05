<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelIdentity extends Model
{
    protected $fillable = [
        'conversation_id',
        'type',
        'external_thread_id',
        'external_user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
