<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    protected $fillable = [
        'integration_type',
        'label',
        'username',
        'password_encrypted',
        'api_key_encrypted',
        'environment',
        'status',
        'location_id',
    ];

    protected $casts = [
        'password_encrypted' => 'encrypted',
        'api_key_encrypted'  => 'encrypted',
    ];

    /**
     * Never expose secrets in JSON / array output.
     */
    protected $hidden = [
        'password_encrypted',
        'api_key_encrypted',
    ];

    // ── Relationships ───────────────────────────────────

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // ── Scopes ──────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('integration_type', $type);
    }

    public function scopeForEnvironment(Builder $query, string $env): Builder
    {
        return $query->where('environment', $env);
    }

    public function scopeForLocation(Builder $query, ?int $locationId): Builder
    {
        return $locationId
            ? $query->where('location_id', $locationId)
            : $query->whereNull('location_id');
    }

    // ── Helpers ─────────────────────────────────────────

    public function isActive(): bool
    {
        return strtolower((string) $this->status) === 'active';
    }

    public function apiKey(): ?string
    {
        return $this->api_key_encrypted ?: null;
    }

    public function password(): ?string
    {
        return $this->password_encrypted ?: null;
    }
}
