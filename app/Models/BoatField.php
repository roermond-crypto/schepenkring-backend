<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BoatField extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_key',
        'labels_json',
        'options_json',
        'field_type',
        'block_key',
        'step_key',
        'sort_order',
        'storage_relation',
        'storage_column',
        'ai_relevance',
        'is_active',
    ];

    protected $casts = [
        'labels_json' => 'array',
        'options_json' => 'array',
        'sort_order' => 'integer',
        'ai_relevance' => 'boolean',
        'is_active' => 'boolean',
        'mappings_count' => 'integer',
        'value_observations_count' => 'integer',
        'value_observations_total' => 'integer',
    ];

    public function priorities(): HasMany
    {
        return $this->hasMany(BoatFieldPriority::class, 'field_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(BoatFieldMapping::class, 'field_id');
    }

    public function valueObservations(): HasMany
    {
        return $this->hasMany(BoatFieldValueObservation::class, 'field_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function labelForLocale(?string $locale = null): string
    {
        $labels = is_array($this->labels_json) ? $this->labels_json : [];
        $normalizedLocale = Str::lower(Str::substr((string) ($locale ?: app()->getLocale() ?: 'en'), 0, 2));

        foreach ([$normalizedLocale, 'en', 'nl', 'de', 'fr'] as $candidate) {
            $value = trim((string) ($labels[$candidate] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return (string) Str::of($this->internal_key)->replace('_', ' ')->title();
    }
}
