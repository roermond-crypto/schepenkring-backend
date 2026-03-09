<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedContact extends Model
{
    protected $fillable = [
        'type',
        'value',
        'reason',
        'blocked_until',
    ];

    protected $casts = [
        'blocked_until' => 'datetime',
    ];
}
