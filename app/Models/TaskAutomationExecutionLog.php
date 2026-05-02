<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAutomationExecutionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rule_id',
        'trigger_event',
        'entity_type',
        'entity_id',
        'result',
        'reason',
        'created_task_ids',
        'matched_conditions',
        'created_at',
    ];

    protected $casts = [
        'created_task_ids' => 'array',
        'matched_conditions' => 'array',
        'created_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(TaskAutomationRule::class, 'rule_id');
    }
}
