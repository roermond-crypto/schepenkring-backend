<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatVideoSetting extends Model
{
    protected $fillable = [
        'yacht_id',
        'auto_publish_social',
        'caption_template',
        'hashtags_template',
        'platforms',
        'video_crop_format',
        'auto_generate_caption',
    ];

    protected $casts = [
        'auto_publish_social' => 'boolean',
        'auto_generate_caption' => 'boolean',
        'platforms' => 'array',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}
