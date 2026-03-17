<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BoatFieldPriority extends Model
{
    use HasFactory;

    public const PRIORITIES = ['primary', 'secondary'];

    protected $fillable = [
        'field_id',
        'boat_type_key',
        'priority',
    ];

    public function boatField(): BelongsTo
    {
        return $this->belongsTo(BoatField::class, 'field_id');
    }

    public function setBoatTypeKeyAttribute(?string $value): void
    {
        $this->attributes['boat_type_key'] = self::normalizeBoatTypeKey($value);
    }

    public function setPriorityAttribute(string $value): void
    {
        $this->attributes['priority'] = Str::lower(trim($value));
    }

    public static function normalizeBoatTypeKey(?string $value): string
    {
        $normalized = Str::of((string) $value)
            ->lower()
            ->trim()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        return $normalized !== '' ? $normalized : 'default';
    }
}
