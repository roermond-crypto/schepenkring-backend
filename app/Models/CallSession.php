<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallSession extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'conversation_id',
        'harbor_id',
        'contact_id',
        'initiated_by_user_id',
        'direction',
        'status',
        'from_number',
        'to_number',
        'call_control_id',
        'call_leg_id',
        'telnyx_call_session_id',
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'billable_seconds',
        'cost_eur',
        'charged_at',
        'recording_url',
        'recording_storage_path',
        'language',
        'transcript_text',
        'latency_first_token_ms',
        'latency_first_audio_ms',
        'latency_total_ms',
        'outcome',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'charged_at' => 'datetime',
        'cost_eur' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'harbor_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(CallSessionTranscript::class, 'call_session_id');
    }
}
