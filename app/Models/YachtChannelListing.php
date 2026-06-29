<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YachtChannelListing extends Model
{
    protected $fillable = [
        'yacht_id',
        'channel_name',
        'is_enabled',
        'auto_publish',
        'status',
        'settings_json',
        'capabilities',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'is_enabled'     => 'boolean',
        'auto_publish'   => 'boolean',
        'settings_json'  => 'array',
        'capabilities'   => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}
