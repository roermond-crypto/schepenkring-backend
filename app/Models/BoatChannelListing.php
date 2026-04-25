<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoatChannelListing extends Model
{
    protected $fillable = [
        'boat_id',
        'channel_name',
        'is_enabled',
        'auto_publish',
        'external_id',
        'external_url',
        'status',
        'payload_hash',
        'settings_json',
        'last_request_payload_json',
        'last_response_payload_json',
        'last_validation_errors_json',
        'published_at',
        'last_sync_at',
        'last_success_at',
        'last_error_at',
        'last_error_message',
        'removed_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'auto_publish' => 'boolean',
        'settings_json' => 'array',
        'last_request_payload_json' => 'array',
        'last_response_payload_json' => 'array',
        'last_validation_errors_json' => 'array',
        'published_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_error_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BoatChannelLog::class, 'boat_channel_listing_id')->latest();
    }

    public function scopeForChannel(Builder $query, string $channelName): Builder
    {
        return $query->where('channel_name', $channelName);
    }
}
