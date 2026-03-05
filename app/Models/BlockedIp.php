<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    protected $fillable = [
        'ip',
        'reason',
        'blocked_until',
    ];

    protected $casts = [
        'blocked_until' => 'datetime',
    ];
}
