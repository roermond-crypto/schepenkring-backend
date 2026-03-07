<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    protected $fillable = [
        'owner_key',
        'scope',
        'idempotency_key',
        'request_hash',
        'response_status',
        'response_body',
        'response_headers',
        'expires_at',
    ];

    protected $casts = [
        'response_headers' => 'array',
        'expires_at' => 'datetime',
    ];
}
