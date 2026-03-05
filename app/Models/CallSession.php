<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function transcripts()
    {
        return $this->hasMany(CallSessionTranscript::class, 'call_session_id');
    }
}
