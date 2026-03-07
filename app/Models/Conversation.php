<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'location_id',
        'boat_id',
        'contact_id',
        'visitor_id',
        'channel',
        'channel_origin',
        'status',
        'priority',
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

    protected $appends = [
        'harbor_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            if (! $conversation->id) {
                $conversation->id = (string) Str::uuid();
            }
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getHarborIdAttribute(): ?int
    {
        return $this->location_id;
    }

    public function setHarborIdAttribute($value): void
    {
        $this->attributes['location_id'] = $value;
    }

    public function getChannelOriginAttribute($value): ?string
    {
        return $value ?? $this->channel;
    }

    public function setChannelOriginAttribute($value): void
    {
        $this->attributes['channel_origin'] = $value;
        if (! array_key_exists('channel', $this->attributes) || $this->attributes['channel'] === null) {
            $this->attributes['channel'] = $value;
        }
    }
}
