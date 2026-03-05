<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Harbor extends Model
{
    protected $appends = ['public_slug'];

    protected $fillable = [
        // HISWA source
        'hiswa_company_id', 'name', 'slug', 'description',
        // Address
        'street_address', 'postal_code', 'city', 'province', 'country',
        // Contact
        'email', 'phone', 'website',
        // Meta
        'facilities', 'tags', 'source_url', 'first_seen_at', 'last_seen_at',
        // Google Geocoding
        'gmaps_place_id', 'gmaps_formatted_address', 'lat', 'lng',
        'address_components', 'geocode_confidence', 'maps_url',
        'geocode_query_hash', 'last_geocode_at',
        // Google Place Details
        'opening_hours_json', 'rating', 'rating_count',
        'primary_phone', 'google_website', 'google_photos',
        'place_details_json', 'last_place_details_fetch_at', 'last_place_photos_fetch_at',
        // Optional third-party enrichment
        'third_party_enrichment', 'last_third_party_enrichment_at',
        // Status
        'needs_review', 'is_published', 'claimed_by_user_id',
        'qr_code_url'
    ];

    protected $casts = [
        'facilities'               => 'array',
        'tags'                     => 'array',
        'address_components'       => 'array',
        'opening_hours_json'       => 'array',
        'google_photos'            => 'array',
        'place_details_json'       => 'array',
        'third_party_enrichment'   => 'array',
        'lat'                      => 'decimal:7',
        'lng'                      => 'decimal:7',
        'rating'                   => 'decimal:1',
        'needs_review'             => 'boolean',
        'is_published'             => 'boolean',
        'first_seen_at'            => 'datetime',
        'last_seen_at'             => 'datetime',
        'last_geocode_at'          => 'datetime',
        'last_place_details_fetch_at' => 'datetime',
        'last_place_photos_fetch_at' => 'datetime',
        'last_third_party_enrichment_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────

    public function pages(): HasMany
    {
        return $this->hasMany(HarborPage::class);
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    // ─── Scopes ───────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeNeedsReview($query)
    {
        return $query->where('needs_review', true);
    }

    public function scopeNeedsGeocode($query)
    {
        return $query->whereNull('gmaps_place_id')
                     ->whereNotNull('city');
    }

    public function scopeNeedsPlaceDetails($query)
    {
        return $query->whereNotNull('gmaps_place_id')
                     ->where(function ($q) {
                         $q->whereNull('last_place_details_fetch_at')
                           ->orWhere('last_place_details_fetch_at', '<', now()->subDays(30));
                     });
    }

    public function scopeNeedsPlacePhotos($query)
    {
        return $query->whereNotNull('gmaps_place_id')
                     ->where(function ($q) {
                         $q->whereNull('last_place_photos_fetch_at')
                           ->orWhere('last_place_photos_fetch_at', '<', now()->subDays(90));
                     });
    }

    public function scopeMissingContacts($query)
    {
        return $query->whereNull('email')
            ->whereNull('phone')
            ->whereNull('primary_phone')
            ->whereNull('website')
            ->whereNull('google_website');
    }

    // ─── Helpers ──────────────────────────────────

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->street_address,
            $this->postal_code,
            $this->city,
            $this->province,
            $this->country,
        ])->filter()->implode(', ');
    }

    public function getPublicSlugAttribute(): string
    {
        if (!empty($this->hiswa_company_id)) {
            return "{$this->slug}-{$this->hiswa_company_id}";
        }

        return "{$this->slug}-{$this->id}";
    }

    public function computeGeocodeQueryHash(): string
    {
        return md5(strtolower($this->full_address));
    }

    public static function generateSlug(string $name, ?string $city = null): string
    {
        $base = $city ? "{$name} {$city}" : $name;
        $slug = Str::slug($base);

        $count = static::where('slug', 'like', "{$slug}%")->count();
        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }
}
