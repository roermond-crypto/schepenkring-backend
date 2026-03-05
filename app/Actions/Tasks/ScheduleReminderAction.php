<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class ScheduleReminderAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskAccessService $access
    ) {
    }

    public function execute(User $actor, Task $task, ?string $reminderAt): Task
    {
        if (! $this->access->canEdit($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $this->tasks->update($task, [
            'reminder_at' => $reminderAt,
            'reminder_sent_at' => null,
        ]);

        return $task->fresh(['assignedTo', 'creator', 'user', 'yacht', 'column']);
    }
}
