<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'browser',
        'os',
        'user_agent',
        'first_seen_at',
        'last_seen_at',
        'last_ip_address',
        'last_ip_country',
        'last_ip_city',
        'last_ip_asn',
        'blocked_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
