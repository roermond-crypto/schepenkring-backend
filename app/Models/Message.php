<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'conversation_id',
        'sender_type',
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
        'employee_id',
        'body',
        'client_message_id',
        'delivery_state',
    ];

    protected $casts = [
        'metadata' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'ai_confidence' => 'decimal:2',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'message_id');
    }
}
