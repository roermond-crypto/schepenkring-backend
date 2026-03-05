<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\NotificationDispatchService;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class RemindTaskAction
{
    public function __construct(
        private TaskAccessService $access,
        private NotificationDispatchService $notifications
    ) {
    }

    public function execute(User $actor, Task $task, array $data): array
    {
        if (! $this->access->canEdit($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $recipient = $task->assignedTo ?: ($task->user ?: $task->creator);
        if (! $recipient) {
            throw ValidationException::withMessages([
                'task' => 'No recipient found for this task.',
            ]);
        }

        $allowRealtime = (bool) ($data['realtime'] ?? true);
        $allowEmail = (bool) ($data['email'] ?? true);

        $notification = $this->notifications->notifyUser(
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
            null,
            $allowRealtime,
            $allowEmail
        );

        return [
            'message' => 'Reminder sent',
            'notification' => $notification,
        ];
    }
}
