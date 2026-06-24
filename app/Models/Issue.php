<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Issue extends Model
{
    public const STATUSES = [
        'new',
        'ai_pending',
        'ai_completed',
        'in_review',
        'fixed',
        'closed',
        'failed',
    ];

    public const AI_STATUSES = [
        'pending',
        'processing',
        'completed',
        'failed',
    ];

    protected $fillable = [
        'user_id',
        'yacht_id',
        'title',
        'description',
        'status',
        'screenshot_path',
        'screenshot_original_name',
        'screenshot_status',
        'page_url',
        'browser',
        'device',
        'logs',
        'ai_status',
        'ai_analysis',
        'ai_summary',
        'ai_priority',
        'ai_suggested_fix',
        'ai_error',
        'ai_analyzed_at',
    ];

    protected $casts = [
        'logs' => 'array',
        'ai_analyzed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}
