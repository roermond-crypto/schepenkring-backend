<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    protected $fillable = [
        'yacht_id',
        'status',
        'template_type',
        'source_image_ids_json',
        'generation_trigger',
        'generation_provider',
        'provider_job_id',
        'provider_status',
        'provider_progress',
        'provider_payload',
        'video_path',
        'video_url',
        'thumbnail_path',
        'thumbnail_url',
        'duration_seconds',
        'file_size_bytes',
        'checksum',
        'caption',
        'error_message',
        'generated_at',
        'whatsapp_status',
        'whatsapp_sent_at',
        'whatsapp_message_id',
        'whatsapp_recipient',
        'whatsapp_error',
    ];

    protected $casts = [
        'source_image_ids_json' => 'array',
        'provider_progress' => 'integer',
        'provider_payload' => 'array',
        'duration_seconds' => 'integer',
        'file_size_bytes' => 'integer',
        'generated_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(VideoPost::class);
    }

    public function getVideoUrlAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }
        if (!$this->video_path) {
            return null;
        }
        return Storage::disk('public')->url($this->video_path);
    }

    public function getThumbnailUrlAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }
        if (!$this->thumbnail_path) {
            return null;
        }
        return Storage::disk('public')->url($this->thumbnail_path);
    }
}
