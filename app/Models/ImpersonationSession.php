<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'impersonator_id',
        'impersonated_id',
        'token_id',
        'started_at',
        'ended_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(\Laravel\Sanctum\PersonalAccessToken::class, 'token_id');
    }
}
