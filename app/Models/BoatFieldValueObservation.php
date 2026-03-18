<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BoatFieldValueObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'source',
        'external_key',
        'external_value',
        'observed_count',
        'last_seen_at',
    ];

    protected $casts = [
        'observed_count' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function boatField(): BelongsTo
    {
        return $this->belongsTo(BoatField::class, 'field_id');
    }

    public function setSourceAttribute(string $value): void
    {
        $this->attributes['source'] = Str::lower(trim($value));
    }
}
