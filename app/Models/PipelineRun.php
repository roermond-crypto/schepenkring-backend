<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineRun extends Model
{
    protected $fillable = [
        'pipeline',
        'processable_type',
        'processable_id',
        'current_step',
        'status',
        'steps_completed',
        'steps_failed',
        'error_log',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'steps_completed' => 'array',
        'steps_failed'    => 'array',
        'error_log'       => 'array',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────

    public function processable()
    {
        return $this->morphTo();
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // ── State Machine ────────────────────────────────────

    public function markRunning(string $step): void
    {
        $this->update([
            'status'       => 'running',
            'current_step' => $step,
            'started_at'   => $this->started_at ?? now(),
        ]);
    }

    public function completeStep(string $step): void
    {
        $completed = $this->steps_completed ?? [];
        $completed[] = $step;

        $this->update([
            'steps_completed' => array_unique($completed),
            'current_step'    => null,
        ]);
    }

    public function failStep(string $step, string $error): void
    {
        $failed = $this->steps_failed ?? [];
        $failed[$step] = $error;

        $errorLog = $this->error_log ?? [];
        $errorLog[] = [
            'step'      => $step,
            'error'     => $error,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update([
            'status'       => 'failed',
            'steps_failed' => $failed,
            'error_log'    => $errorLog,
            'current_step' => null,
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status'       => 'completed',
            'current_step' => null,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if a specific step was already completed (for resume support).
     */
    public function hasCompletedStep(string $step): bool
    {
        return in_array($step, $this->steps_completed ?? []);
    }
}
