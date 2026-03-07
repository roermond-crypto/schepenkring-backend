<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopilotAuditEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'source',
        'stage',
        'input_text',
        'resolved_action_candidates',
        'matching_detail',
        'selected_action_id',
        'selected_action_params',
        'deeplink_returned',
        'confidence',
        'status',
        'failure_reason',
        'validation_result',
        'execution_result',
        'duration_ms',
        'user_correction_action_id',
        'request_id',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'resolved_action_candidates' => 'array',
        'matching_detail' => 'array',
        'selected_action_params' => 'array',
        'validation_result' => 'array',
        'execution_result' => 'array',
        'confidence' => 'decimal:3',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Copilot audit events are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Copilot audit events are immutable.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
