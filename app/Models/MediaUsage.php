<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaUsage extends Model
{
    protected $fillable = [
        'media_id',
        'usable_type',
        'usable_id',
        'field_key',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
