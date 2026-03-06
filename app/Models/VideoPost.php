<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoPost extends Model
{
    protected $fillable = [
        'video_id',
        'yext_post_id',
        'publishers',
        'scheduled_at',
        'published_at',
        'status',
        'views',
        'impressions',
        'clicks',
        'engagement',
        'last_synced_at',
        'error_message',
        'yext_account_id',
        'yext_entity_id',
        'attempts',
    ];

    protected $casts = [
        'publishers' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'views' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'engagement' => 'integer',
        'attempts' => 'integer',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SocialLog::class);
    }
}
