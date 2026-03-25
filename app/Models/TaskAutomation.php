<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAutomation extends Model
{
    protected $fillable = [
        'template_id',
        'trigger_event',
        'related_type',
        'related_id',
        'assigned_user_id',
        'due_at',
        'status',
        'created_task_id',
        'last_error',
        'location_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskAutomationTemplate::class, 'template_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'created_task_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function relatedYacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'related_id');
    }
}
