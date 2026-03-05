<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BoatVideo extends Model
{
    protected $fillable = [
        'yacht_id',
        'video_url',
        'thumbnail_url',
        'duration',
        'format',
        'status',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function getVideoUrlAttribute($value): ?string
    {
        if (!empty($value) && str_starts_with($value, 'http')) {
            return $value;
        }
        if (!$value) {
            return null;
        }
        return Storage::disk('public')->url($value);
    }

    public function getThumbnailUrlAttribute($value): ?string
    {
        if (!empty($value) && str_starts_with($value, 'http')) {
            return $value;
        }
        if (!$value) {
            return null;
        }
        return Storage::disk('public')->url($value);
    }
}
