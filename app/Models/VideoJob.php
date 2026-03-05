<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoJob extends Model
{
    protected $fillable = [
        'yacht_id',
        'user_id',
        'status',
        'video_path',
        'error_log',
        'duration_seconds',
        'file_size_bytes',
        'music_track',
        'has_voiceover',
        'voiceover_path',
        'image_count',
        'progress',
    ];

    protected $casts = [
        'has_voiceover' => 'boolean',
        'image_count' => 'integer',
        'progress' => 'integer',
        'duration_seconds' => 'integer',
        'file_size_bytes' => 'integer',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the public video URL.
     */
    public function getVideoUrlAttribute(): ?string
    {
        if (!$this->video_path) return null;
        return url('storage/' . $this->video_path);
    }
}
