<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BoatIntakeFile extends Model
{
    protected $fillable = [
        'boat_intake_id',
        'type',
        'original_name',
        'path',
        'mime_type',
        'size',
        'disk',
        'variant',
        'sort_order',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function intake(): BelongsTo
    {
        return $this->belongsTo(BoatIntake::class, 'boat_intake_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isPhoto(): bool
    {
        return $this->type === 'photo';
    }
}
