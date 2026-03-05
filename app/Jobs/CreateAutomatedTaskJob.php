<?php

namespace App\Jobs;

use App\Events\TaskCreated;
use App\Models\Task;
use App\Models\TaskAutomation;
use App\Models\User;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateAutomatedTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $automationId)
    {
    }

    public function handle(): void
    {
        $automation = TaskAutomation::with('template')->find($this->automationId);
        if (!$automation || $automation->status !== 'processing') {
            return;
        }

        $template = $automation->template;
        if (!$template || !$template->is_active) {
            $automation->update(['status' => 'canceled']);
            return;
        }

        $assigneeId = $automation->assigned_user_id ?: $this->getDefaultAdminId();
        if (!$assigneeId) {
            $automation->update([
                'status' => 'failed',
                'last_error' => 'No assignee could be resolved',
            ]);
            return;
        }

        $actor = $this->getDefaultAdminUser() ?? User::find($assigneeId);

        try {
            $task = DB::transaction(function () use ($automation, $template, $assigneeId, $actor) {
                $task = Task::create([
                    'title' => $template->title,
                    'description' => $template->description,
                    'priority' => $template->priority,
                    'status' => 'New',
                    'assignment_status' => $assigneeId ? 'pending' : null,
                    'assigned_to' => $assigneeId,
                    'user_id' => null,
                    'created_by' => $actor?->id,
                    'yacht_id' => $automation->related_type === \App\Models\Yacht::class ? $automation->related_id : null,
                    'due_date' => Carbon::parse($automation->due_at)->toDateString(),
                    'type' => $assigneeId ? 'assigned' : 'personal',
                ]);

                $automation->update([
                    'status' => 'completed',
                    'created_task_id' => $task->id,
                ]);

                return $task;
            });

            if ($actor) {
                event(new TaskCreated($task, $actor, [
                    'realtime' => $template->notification_enabled,
                    'email' => $template->email_enabled,
                ]));
            }

            $this->maybeScheduleNextRecurring($automation);
        } catch (\Throwable $e) {
            Log::error('Automated task creation failed', [
                'automation_id' => $automation->id,
                'error' => $e->getMessage(),
            ]);
            $automation->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);
        }
    }

    private function maybeScheduleNextRecurring(TaskAutomation $automation): void
    {
        $template = $automation->template;
        if (!$template || $template->schedule_type !== 'recurring' || empty($template->cron_expression)) {
            return;
        }

        $cron = CronExpression::factory($template->cron_expression);
        $next = Carbon::instance($cron->getNextRunDate(Carbon::now(), 0, true));

        TaskAutomation::create([
            'template_id' => $template->id,
            'trigger_event' => $automation->trigger_event,
            'related_type' => $automation->related_type,
            'related_id' => $automation->related_id,
            'assigned_user_id' => $automation->assigned_user_id,
            'due_at' => $next,
            'status' => 'pending',
        ]);
    }

    private function getDefaultAdminId(): ?int
    {
        return User::where('role', 'Admin')
            ->where('status', 'Active')
            ->value('id');
    }

    private function getDefaultAdminUser(): ?User
    {
        return User::where('role', 'Admin')
            ->where('status', 'Active')
            ->first();
    }
}
