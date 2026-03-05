<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CallSessionTranscript extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'call_session_id',
        'conversation_id',
        'speaker',
        'text',
        'started_at',
        'ended_at',
        'sequence',
        'is_final',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_final' => 'boolean',
        'metadata' => 'array',
    ];

    public function callSession()
    {
        return $this->belongsTo(CallSession::class, 'call_session_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
