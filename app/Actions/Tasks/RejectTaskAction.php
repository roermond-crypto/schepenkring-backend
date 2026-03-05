<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskActivityLogRepository;
use App\Repositories\TaskRepository;
use App\Services\ActionSecurity;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class RejectTaskAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskActivityLogRepository $activityLogs,
        private TaskAccessService $access,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, Task $task): Task
    {
        $before = $task->toArray();

        if (! $this->access->canView($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        if ($task->assigned_to !== $actor->id) {
            throw new AuthorizationException('Unauthorized');
        }

        $this->tasks->update($task, [
            'assignment_status' => 'rejected',
        ]);

        $this->activityLogs->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => 'rejected',
            'description' => 'Task rejected',
            'location_id' => $task->location_id,
        ]);

        $updated = $task->fresh(['assignedTo', 'creator', 'user', 'yacht', 'column']);

        $this->security->log('task.reject', RiskLevel::LOW, $actor, $task, [
            'assignment_status' => 'rejected',
        ], [
            'location_id' => $updated->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
