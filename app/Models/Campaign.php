<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'type',
        'status',
        'location_id',
        'target_criteria',
        'email_template_key',
        'voice_agent_id',
        'calling_hours',
        'max_call_attempts',
        'retry_delay_hours',
        'spend_cap_eur',
        'spend_to_date_eur',
        'min_score_to_call',
        'created_by_user_id',
    ];

    protected $casts = [
        'target_criteria' => 'array',
        'calling_hours' => 'array',
        'spend_cap_eur' => 'decimal:2',
        'spend_to_date_eur' => 'decimal:2',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(CampaignTarget::class);
    }

    public function callSessions(): HasMany
    {
        return $this->hasMany(CallSession::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOverSpendCap(): bool
    {
        return $this->spend_cap_eur !== null && (float) $this->spend_to_date_eur >= (float) $this->spend_cap_eur;
    }

    /**
     * Whether $when falls within this campaign's configured calling window.
     * No calling_hours configured means "always allowed" (campaign owner is
     * responsible for setting one before going live).
     */
    public function isWithinCallingHours(\DateTimeInterface $when): bool
    {
        if (! $this->calling_hours) {
            return true;
        }

        $timezone = $this->calling_hours['timezone'] ?? 'Europe/Amsterdam';
        $local = \Illuminate\Support\Carbon::instance($when)->setTimezone($timezone);

        $days = $this->calling_hours['days'] ?? [1, 2, 3, 4, 5];
        if (! in_array((int) $local->dayOfWeekIso, $days, true)) {
            return false;
        }

        $start = $this->calling_hours['start'] ?? '09:00';
        $end = $this->calling_hours['end'] ?? '18:00';

        return $local->format('H:i') >= $start && $local->format('H:i') <= $end;
    }
}
