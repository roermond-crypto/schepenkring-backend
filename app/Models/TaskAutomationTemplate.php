<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskAutomationTemplate extends Model
{
    protected $fillable = [
        'name',
        'trigger_event',
        'schedule_type',
        'delay_value',
        'delay_unit',
        'fixed_at',
        'cron_expression',
        'title',
        'description',
        'priority',
        'default_assignee_type',
        'notification_enabled',
        'email_enabled',
        'is_active',
        'boat_type_filter',
        'location_id',
    ];

    protected $casts = [
        'fixed_at' => 'datetime',
        'notification_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'is_active' => 'boolean',
        'boat_type_filter' => 'array',
    ];

    public function automations(): HasMany
    {
        return $this->hasMany(TaskAutomation::class, 'template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskAutomationTemplateItem::class, 'template_id')->orderBy('position');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}

