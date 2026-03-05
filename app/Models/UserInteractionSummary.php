<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInteractionSummary extends Model
{
    protected $fillable = [
        'user_id',
        'summary',
        'source',
        'last_activity_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_activity_at' => 'datetime',
    ];
}
