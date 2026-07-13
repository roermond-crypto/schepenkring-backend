<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `credentials` json column holds all secret/connection fields for the
 * Advanced tab and is never returned raw once set — see `getCredentialsAttribute()`.
 * Sub-schema:
 *   - api_key       (string, shown as-is)
 *   - api_secret    (string, masked on read)
 *   - webhook_url   (string, shown as-is)
 *   - webhook_secret(string, masked on read)
 *   - debug_mode    (bool)
 */
class Platform extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'website_url',
        'category',
        'export_method',
        'feed_url',
        'api_url',
        'credentials',
        'openmarine_enabled',
        'openmarine_dealer_id',
        'openmarine_version',
        'openmarine_category_map',
        'supported_countries',
        'supported_languages',
        'contact_name',
        'contact_email',
        'notes',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'credentials'            => 'array',
        'openmarine_category_map'=> 'array',
        'supported_countries'    => 'array',
        'supported_languages'    => 'array',
        'openmarine_enabled'     => 'boolean',
        'is_active'              => 'boolean',
    ];

    private const MASKED_CREDENTIAL_KEYS = ['api_secret', 'webhook_secret'];

    public const MASK_PLACEHOLDER = '__unchanged__';

    // Expose frontend-friendly aliases in JSON without an extra database column
    protected $appends = ['is_openmarine_enabled', 'platform_type', 'has_api_secret', 'has_webhook_secret'];

    public function getIsOpenmarineEnabledAttribute(): bool
    {
        return (bool) $this->openmarine_enabled;
    }

    public function getPlatformTypeAttribute(): string
    {
        return $this->category ?? 'marketplace';
    }

    /**
     * Never expose raw api_secret/webhook_secret. Callers that need to know
     * whether a secret is set use `has_api_secret`/`has_webhook_secret` below.
     */
    public function getCredentialsAttribute($value): array
    {
        $decoded = is_string($value) ? (json_decode($value, true) ?? []) : ($value ?? []);

        foreach (self::MASKED_CREDENTIAL_KEYS as $key) {
            if (! empty($decoded[$key])) {
                $decoded[$key] = '••••' . substr((string) $decoded[$key], -4);
            }
        }

        return $decoded;
    }

    public function hasCredentialSecret(string $key): bool
    {
        $raw = $this->getRawOriginal('credentials');
        $decoded = is_string($raw) ? (json_decode($raw, true) ?? []) : ($raw ?? []);

        return ! empty($decoded[$key]);
    }

    public function getHasApiSecretAttribute(): bool
    {
        return $this->hasCredentialSecret('api_secret');
    }

    public function getHasWebhookSecretAttribute(): bool
    {
        return $this->hasCredentialSecret('webhook_secret');
    }

    public function boatPublications(): HasMany
    {
        return $this->hasMany(BoatPlatformPublication::class);
    }
}
