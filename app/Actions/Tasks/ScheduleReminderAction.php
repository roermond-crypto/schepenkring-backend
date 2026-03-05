<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use App\Services\ActionSecurity;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class ScheduleReminderAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskAccessService $access,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, Task $task, ?string $reminderAt): Task
    {
        $before = $task->toArray();

        if (! $this->access->canEdit($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $this->tasks->update($task, [
            'reminder_at' => $reminderAt,
            'reminder_sent_at' => null,
        ]);

        $updated = $task->fresh(['assignedTo', 'creator', 'user', 'yacht', 'column']);

        $this->security->log('task.reminder.schedule', RiskLevel::LOW, $actor, $task, [
            'reminder_at' => $reminderAt,
        ], [
            'location_id' => $updated->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
