<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_key',
        'idempotency_key',
        'payload_json',
        'processing_at',
        'processed_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'processing_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
