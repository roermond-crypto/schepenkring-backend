<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskSession extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'phone_number',
        'language',
        'vonage_call_id',
        'openai_session_id',
        'status',
        'tags',
        'events',
        'transcript',
    ];

    protected $casts = [
        'tags' => 'array',
        'events' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
