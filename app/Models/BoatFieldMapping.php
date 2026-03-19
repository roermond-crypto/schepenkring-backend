<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BoatFieldMapping extends Model
{
    use HasFactory;

    public const SOURCES = ['yachtshift', 'scrape', 'future_import'];
    public const MATCH_TYPES = ['exact', 'contains', 'regex', 'manual'];

    protected $fillable = [
        'field_id',
        'source',
        'external_key',
        'external_value',
        'normalized_value',
        'match_type',
    ];

    public function boatField(): BelongsTo
    {
        return $this->belongsTo(BoatField::class, 'field_id');
    }

    public function setSourceAttribute(string $value): void
    {
        $this->attributes['source'] = Str::lower(trim($value));
    }

    public function setMatchTypeAttribute(string $value): void
    {
        $this->attributes['match_type'] = Str::lower(trim($value));
    }
}
