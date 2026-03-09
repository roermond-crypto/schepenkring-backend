<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'location_id',
        'boat_id',
        'contact_id',
        'visitor_id',
        'channel',
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
        'assigned_employee_id',
        'lead_id',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function channelIdentities(): HasMany
    {
        return $this->hasMany(ChannelIdentity::class, 'conversation_id');
    }

    public function callSessions(): HasMany
    {
        return $this->hasMany(CallSession::class, 'conversation_id');
    }

    public function getHarborIdAttribute(): ?int
    {
        return $this->location_id;
    }

    public function setHarborIdAttribute(?int $value): void
    {
        $this->attributes['location_id'] = $value;
    }
}
