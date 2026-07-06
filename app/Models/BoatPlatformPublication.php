<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatPlatformPublication extends Model
{
    protected $fillable = [
        'yacht_id',
        'platform_id',
        'enabled',
        'external_platform_id',
        'status',
        'last_sync_at',
        'last_success_at',
        'last_error_at',
        'last_error_message',
        'last_payload_hash',
        'retry_count',
    ];

    protected $casts = [
        'enabled'         => 'boolean',
        'last_sync_at'    => 'datetime',
        'last_success_at' => 'datetime',
        'last_error_at'   => 'datetime',
        'retry_count'     => 'integer',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
