<?php

namespace App\Console\Commands;

use App\Mail\TaskReminderMail;
use App\Models\Task;
use App\Services\NotificationDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendTaskReminders extends Command
{
    protected $signature = 'tasks:send-reminders';
    protected $description = 'Send scheduled task reminders';

    public function handle(): int
    {
        $now = now();

        $due = Task::with(['assignedTo', 'creator', 'user'])
            ->whereNotNull('reminder_at')
            ->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', $now)
            ->where('status', '!=', 'Done')
            ->orderBy('reminder_at')
            ->limit(200)
            ->get();

        if ($due->isEmpty()) {
            return Command::SUCCESS;
        }

        $service = new NotificationDispatchService();

        foreach ($due as $task) {
            try {
                $recipient = $task->assignedTo ?: ($task->user ?: $task->creator);
                $actor = $task->creator ?: ($task->assignedTo ?: $task->user);

                if (!$recipient || !$actor) {
                    Log::warning('Task reminder skipped (no recipient/actor)', [
                        'task_id' => $task->id,
                    ]);
                    continue;
                }

                $email = new TaskReminderMail($task, $actor);

                $service->notifyUser(
                    $recipient,
                    'info',
                    'Task reminder',
                    "Reminder: {$task->title}",
                    [
                        'task_id' => $task->id,
                        'task_type' => $task->type,
                        'assignment_status' => $task->assignment_status,
                        'related_type' => $task->yacht_id ? 'yacht' : null,
                        'related_id' => $task->yacht_id,
                    ],
                    $email,
                    true,
                    true
                );

                $task->update(['reminder_sent_at' => $now]);
            } catch (\Throwable $e) {
                Log::error('Task reminder send failed', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Command::SUCCESS;
    }
}
