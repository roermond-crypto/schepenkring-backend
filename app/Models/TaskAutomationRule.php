<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskAutomationRule extends Model
{
    protected $fillable = [
        'name',
        'internal_code',
        'trigger_event',
        'is_active',
        'target_role',
        'target_user_type',
        'assignee_rule',
        'boat_types',
        'boat_year_from',
        'boat_year_to',
        'location_filter',
        'visibility_delay_hours',
        'visibility_status',
        'visibility_status_source',
        'actionable_delay_hours',
        'actionable_status',
        'actionable_status_source',
        'actionable_requires_internal_tasks_completed',
        'assigned_user_id',
        'location_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'boat_types' => 'array',
        'boat_year_from' => 'integer',
        'boat_year_to' => 'integer',
        'visibility_delay_hours' => 'integer',
        'actionable_delay_hours' => 'integer',
        'actionable_requires_internal_tasks_completed' => 'boolean',
    ];

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(
            TaskAutomationTemplate::class,
            'task_automation_rule_template',
            'rule_id',
            'template_id'
        )->withPivot('sort_order')->orderBy('task_automation_rule_template.sort_order');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TaskAutomationExecutionLog::class, 'rule_id');
    }
}
