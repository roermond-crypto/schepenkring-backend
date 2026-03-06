<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformError extends Model
{
    protected $fillable = [
        'reference_code',
        'sentry_issue_id',
        'title',
        'message',
        'level',
        'project',
        'environment',
        'release',
        'source',
        'route',
        'url',
        'occurrences_count',
        'users_affected',
        'first_seen_at',
        'last_seen_at',
        'status',
        'tags',
        'last_event_sample_json',
        'ai_user_message_nl',
        'ai_user_message_en',
        'ai_user_message_de',
        'ai_dev_summary',
        'ai_category',
        'ai_severity',
        'ai_user_steps',
        'ai_suggested_checks',
        'assigned_to_user_id',
        'internal_note',
        'ignore_until',
        'ignore_release',
        'resolved_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'last_event_sample_json' => 'array',
        'ai_user_steps' => 'array',
        'ai_suggested_checks' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'ignore_until' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (!$model->reference_code) {
                $model->reference_code = 'ERR-' . strtoupper(Str::random(6));
            }
        });
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function reports()
    {
        return $this->hasMany(IssueReport::class, 'platform_error_id');
    }
}
