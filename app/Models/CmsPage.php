<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPage extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEW = 'review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ARCHIVED = 'archived';

    public const LOCALE_STATUS_MISSING = 'missing';
    public const LOCALE_STATUS_DRAFT = 'draft';
    public const LOCALE_STATUS_NEEDS_REVIEW = 'needs_review';
    public const LOCALE_STATUS_APPROVED = 'approved';
    public const LOCALE_STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'slug',
        'name',
        'status',
        'locale_status',
        'scheduled_publish_at',
        'published_at',
        'seo',
        'current_version',
        'created_by_id',
    ];

    protected $casts = [
        'seo' => 'array',
        'locale_status' => 'array',
        'scheduled_publish_at' => 'datetime',
        'published_at' => 'datetime',
        'current_version' => 'integer',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(CmsSection::class)->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsPageVersion::class)->orderByDesc('version');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }
}
