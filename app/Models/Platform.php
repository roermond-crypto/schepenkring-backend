<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'website_url',
        'type',
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

    // Expose frontend-friendly aliases in JSON without an extra database column
    protected $appends = ['is_openmarine_enabled', 'platform_type'];

    public function getIsOpenmarineEnabledAttribute(): bool
    {
        return (bool) $this->openmarine_enabled;
    }

    public function getPlatformTypeAttribute(): string
    {
        return $this->type ?? 'openmarine';
    }

    public function boatPublications(): HasMany
    {
        return $this->hasMany(BoatPlatformPublication::class);
    }
}
