<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificationAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'status',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
