<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogValue extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'field_key',
        'value',
        'normalized_value',
        'usage_count',
        'status',
        'merged_into_id',
        'parent_value_id',
        'created_via',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'meta' => 'array',
    ];

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function mergedFrom(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    public function parentValue(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_value_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_value_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForField(Builder $query, string $fieldKey): Builder
    {
        return $query->where('field_key', $fieldKey);
    }
}
