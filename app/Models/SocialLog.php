<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialLog extends Model
{
    protected $fillable = [
        'video_post_id',
        'provider',
        'event',
        'request_payload',
        'response_payload',
        'status_code',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'status_code' => 'integer',
    ];

    public function videoPost(): BelongsTo
    {
        return $this->belongsTo(VideoPost::class);
    }
}
