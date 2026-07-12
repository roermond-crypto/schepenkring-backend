<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_OPTIMIZED = 'optimized';
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    protected $fillable = [
        'disk_path',
        'thumb_path',
        'original_name',
        'mime_type',
        'file_size',
        'width',
        'height',
        'alt_text',
        'caption',
        'seo_title',
        'focal_point_x',
        'focal_point_y',
        'crop_data',
        'status',
        'ai_alt_text_is_draft',
        'ai_seo_title_is_draft',
        'created_by_id',
    ];

    protected $casts = [
        'alt_text' => 'array',
        'caption' => 'array',
        'seo_title' => 'array',
        'crop_data' => 'array',
        'focal_point_x' => 'float',
        'focal_point_y' => 'float',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'ai_alt_text_is_draft' => 'boolean',
        'ai_seo_title_is_draft' => 'boolean',
    ];

    protected $appends = ['url', 'thumb_url'];

    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->resolvePublicUrl($this->disk_path);
    }

    public function getThumbUrlAttribute(): ?string
    {
        return $this->resolvePublicUrl($this->thumb_path) ?? $this->url;
    }

    private function resolvePublicUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value) === 1) {
            return $value;
        }

        return Storage::disk('public')->url(ltrim($value, '/'));
    }
}
