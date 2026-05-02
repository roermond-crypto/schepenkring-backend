<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoPlan extends Model
{
    protected $fillable = [
        'yacht_id', 'template_id', 'variation', 'status',
        'ai_input_json', 'ai_output_json', 'final_plan_json',
        'validation_errors', 'render_job_id', 'render_output_url',
        'render_started_at', 'render_completed_at',
        'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = [
        'ai_input_json'       => 'array',
        'ai_output_json'      => 'array',
        'final_plan_json'     => 'array',
        'validation_errors'   => 'array',
        'approved_at'         => 'datetime',
        'render_started_at'   => 'datetime',
        'render_completed_at' => 'datetime',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(VideoTemplate::class, 'template_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
