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

class UpdateTaskStatusAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskActivityLogRepository $activityLogs,
        private TaskAccessService $access,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, Task $task, string $status): Task
    {
        $before = $task->toArray();

        if (! $this->access->canEdit($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $this->tasks->update($task, [
            'status' => $status,
        ]);

        $this->activityLogs->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => 'status_updated',
            'description' => "Status changed to {$status}",
            'location_id' => $task->location_id,
        ]);

        $updated = $task->fresh(['assignedTo', 'creator', 'user', 'yacht', 'column']);

        $this->security->log('task.status.update', RiskLevel::LOW, $actor, $task, [
            'status' => $status,
        ], [
            'location_id' => $updated->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
