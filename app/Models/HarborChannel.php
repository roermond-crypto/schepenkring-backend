<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HarborChannel extends Model
{
    protected $fillable = [
        'harbor_id',
        'channel',
        'provider',
        'from_number',
        'api_key_encrypted',
        'webhook_token',
        'webhook_secret',
        'status',
        'metadata',
    ];

    protected $casts = [
        'api_key_encrypted' => 'encrypted',
        'metadata' => 'array',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'harbor_id');
    }

    public function isActive(): bool
    {
        return strtolower((string) $this->status) === 'active';
    }

    public function apiKey(): ?string
    {
        return $this->api_key_encrypted ?: null;
    }
}
