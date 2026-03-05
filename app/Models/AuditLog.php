<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        'risk_level',
        'actor_id',
        'impersonator_id',
        'target_type',
        'target_id',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }
}
