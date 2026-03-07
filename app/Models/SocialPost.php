<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    protected $fillable = [
        'yacht_id',
        'platform',
        'post_id',
        'status',
        'published_at',
        'error_message',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}
