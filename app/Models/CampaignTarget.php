<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignTarget extends Model
{
    protected $fillable = [
        'campaign_id',
        'target_type',
        'target_id',
        'status',
        'score',
        'call_attempts',
        'last_action_at',
        'next_action_at',
        'suppression_reason',
        'metadata',
    ];

    protected $casts = [
        'last_action_at' => 'datetime',
        'next_action_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function emailEvents(): HasMany
    {
        return $this->hasMany(EmailEvent::class);
    }

    /**
     * The underlying entity this target represents — a Lead, User,
     * Contact, or (for harbor/location outreach campaigns, spec §5) a
     * Location. Not a true polymorphic relation (no morph map) since each
     * target type needs different contact-resolution logic anyway; callers
     * that need the model should use this, not a raw where() on target_id.
     */
    public function targetModel(): Lead|User|Contact|Location|null
    {
        return match ($this->target_type) {
            'lead' => Lead::find($this->target_id),
            'user' => User::find($this->target_id),
            'contact' => Contact::find($this->target_id),
            'location' => Location::find($this->target_id),
            default => null,
        };
    }

    public function isSuppressed(): bool
    {
        return $this->status === 'suppressed';
    }
}
