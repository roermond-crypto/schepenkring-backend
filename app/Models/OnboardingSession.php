<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingSession extends Model
{
    protected $fillable = [
        'user_id',
        'current_step',
        'last_step_at',
        'ip_address',
        'user_agent',
        'completed',
    ];

    protected $casts = [
        'last_step_at' => 'datetime',
        'completed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
