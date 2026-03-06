<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'conversation_id',
        'sender_type',
        'employee_id',
        'body',
        'text',
        'language',
        'channel',
        'external_message_id',
        'message_type',
        'status',
        'ai_confidence',
        'metadata',
        'delivered_at',
        'read_at',
        'client_message_id',
        'delivery_state',
    ];

    protected $casts = [
        'metadata' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'ai_confidence' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if (! $message->id) {
                $message->id = (string) Str::uuid();
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'message_id');
    }

    public function getTextAttribute($value): ?string
    {
        return $value ?? $this->body;
    }

    public function setTextAttribute($value): void
    {
        $this->attributes['text'] = $value;
        if (! array_key_exists('body', $this->attributes) || $this->attributes['body'] === null) {
            $this->attributes['body'] = $value;
        }
    }
}
