<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class BoatTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand',
        'model',
        'year_min',
        'year_max',
        'match_level',
        'version',
        'source_boat_ids',
        'source_boat_count',
        'fields_json',
        'known_values_json',
        'required_fields_json',
        'optional_fields_json',
        'missing_fields_json',
        'field_stats_json',
        'is_active',
    ];

    protected $casts = [
        'year_min' => 'integer',
        'year_max' => 'integer',
        'version' => 'integer',
        'source_boat_ids' => 'array',
        'source_boat_count' => 'integer',
        'fields_json' => 'array',
        'known_values_json' => 'array',
        'required_fields_json' => 'array',
        'optional_fields_json' => 'array',
        'missing_fields_json' => 'array',
        'field_stats_json' => 'array',
        'is_active' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────

    /**
     * Yachts that were created using this template.
     */
    public function yachts(): HasMany
    {
        return $this->hasMany(Yacht::class, 'template_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBrandModel(Builder $query, string $brand, string $model): Builder
    {
        return $query->where('brand', $brand)->where('model', $model);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * Get the pre-filled form values from this template.
     * Returns an associative array of field_key => value for fields
     * that are consistently filled across source boats.
     */
    public function getPrefilledValues(): array
    {
        return is_array($this->known_values_json) ? $this->known_values_json : [];
    }

    /**
     * Get all fields detected across the source boats.
     */
    public function getRequiredFields(): array
    {
        return is_array($this->required_fields_json) ? $this->required_fields_json : [];
    }

    /**
     * Get optional fields (present in 30-80% of source boats).
     */
    public function getOptionalFields(): array
    {
        return is_array($this->optional_fields_json) ? $this->optional_fields_json : [];
    }

    /**
     * Get fields that are missing from most source boats (need AI fill).
     */
    public function getMissingFields(): array
    {
        return is_array($this->missing_fields_json) ? $this->missing_fields_json : [];
    }

    /**
     * Check if this template covers a given year.
     */
    public function coversYear(?int $year): bool
    {
        if ($year === null) {
            return true; // no year constraint
        }

        if ($this->year_min !== null && $year < $this->year_min) {
            return false;
        }

        if ($this->year_max !== null && $year > $this->year_max) {
            return false;
        }

        return true;
    }
}
