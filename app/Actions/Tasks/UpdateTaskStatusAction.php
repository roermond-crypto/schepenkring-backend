<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskActivityLogRepository;
use App\Repositories\TaskRepository;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateTaskStatusAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskActivityLogRepository $activityLogs,
        private TaskAccessService $access
    ) {
    }

    public function execute(User $actor, Task $task, string $status): Task
    {
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

        return $task->fresh(['assignedTo', 'creator', 'user', 'yacht', 'column']);
    }
}
