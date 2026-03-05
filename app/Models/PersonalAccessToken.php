<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'device_id',
        'device_name',
        'browser',
        'os',
        'user_agent',
        'ip_address',
        'ip_country',
        'ip_city',
        'ip_asn',
        'auth_strength',
        'first_seen_at',
        'last_seen_at',
        'last_verified_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_verified_at' => 'datetime',
    ];
}
