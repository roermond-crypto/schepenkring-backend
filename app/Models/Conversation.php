<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'harbor_id',
        'boat_id',
        'contact_id',
        'visitor_id',
        'status',
        'priority',
        'channel_origin',
        'ai_mode',
        'language_preferred',
        'language_detected',
        'page_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'ref_code',
        'last_message_at',
        'last_customer_message_at',
        'last_inbound_at',
        'window_expires_at',
        'last_staff_message_at',
        'last_call_at',
        'first_response_due_at',
        'assigned_to',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'window_expires_at' => 'datetime',
        'last_staff_message_at' => 'datetime',
        'last_call_at' => 'datetime',
        'first_response_due_at' => 'datetime',
    ];

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function boat()
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    public function channelIdentities()
    {
        return $this->hasMany(ChannelIdentity::class, 'conversation_id');
    }

    public function events()
    {
        return $this->hasMany(ConversationEvent::class, 'conversation_id');
    }

    public function callSessions()
    {
        return $this->hasMany(CallSession::class, 'conversation_id');
    }
}
