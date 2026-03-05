<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Models\User;
use App\Services\NotificationDispatchService;

class SendTaskNotification
{
    public function handle(TaskCreated $event): void
    {
        $task = $event->task;
        $actor = $event->actor;
        $allowRealtime = $event->options['realtime'] ?? true;
        $allowEmail = $event->options['email'] ?? true;

        $recipient = null;
        $title = 'New task created';
        $message = "New task created by {$actor->name}: {$task->title}";

        if (! empty($task->assigned_to)) {
            $recipient = User::find($task->assigned_to);
            $title = 'New task assigned';
            $message = "You have a new task: {$task->title}";
        } else {
            $recipient = $actor;
        }

        if (! $recipient) {
            return;
        }

        $service = new NotificationDispatchService();
        $service->notifyUser(
            $recipient,
            'info',
            $title,
            $message,
            [
                'task_id' => $task->id,
                'task_type' => $task->type,
                'assignment_status' => $task->assignment_status,
                'related_type' => $task->yacht_id ? 'yacht' : null,
                'related_id' => $task->yacht_id,
                'entity_type' => 'task',
                'entity_id' => $task->id,
                'url' => "/dashboard/tasks/{$task->id}",
            ],
            null,
            $allowRealtime,
            $allowEmail,
            $task->location_id
        );
    }
}
