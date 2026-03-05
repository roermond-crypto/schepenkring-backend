<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'user_id',
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
