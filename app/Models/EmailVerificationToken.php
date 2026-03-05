<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificationToken extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'code_hash',
        'expires_at',
        'attempts',
        'locked_until',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'locked_until' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
