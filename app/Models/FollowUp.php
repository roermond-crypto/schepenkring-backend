<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'next_action',
        'due_at',
        'retry_count',
        'last_outcome',
        'assigned_employee_id',
        'suppression_reason',
        'ai_summary',
        'status',
        'related_yacht_id',
        'related_deal_id',
        'related_chat_thread_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
    ];

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function relatedYacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'related_yacht_id');
    }

    public function relatedDeal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'related_deal_id');
    }

    public function relatedChatThread(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'related_chat_thread_id');
    }

    public function isDue(): bool
    {
        return $this->status === 'open' && ($this->due_at === null || $this->due_at->isPast());
    }
}
