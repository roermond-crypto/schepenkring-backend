<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionAudit extends Model
{
    protected $table = 'action_audits';

    protected $fillable = [
        'action_key',
        'risk_level',
        'user_id',
        'entity_type',
        'entity_id',
        'device_id',
        'ip_address',
        'ip_country',
        'user_agent',
        'request_id',
        'request_method',
        'request_path',
        'request_hash',
        'old_state',
        'new_state',
        'metadata',
    ];

    protected $casts = [
        'old_state' => 'array',
        'new_state' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Action audits are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Action audits are immutable.');
        });
    }
}
