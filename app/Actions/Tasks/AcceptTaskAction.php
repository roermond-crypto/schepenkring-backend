<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskActivityLogRepository;
use App\Repositories\TaskRepository;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class AcceptTaskAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskActivityLogRepository $activityLogs,
        private TaskAccessService $access
    ) {
    }

    public function execute(User $actor, Task $task): Task
    {
        if (! $this->access->canView($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        if ($task->assigned_to !== $actor->id) {
            throw new AuthorizationException('Unauthorized');
        }

        $this->tasks->update($task, [
            'assignment_status' => 'accepted',
            'status' => $task->status === 'New' ? 'Pending' : $task->status,
        ]);

        $this->activityLogs->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => 'accepted',
            'description' => 'Task accepted',
            'location_id' => $task->location_id,
        ]);

        return $task->fresh(['assignedTo', 'creator', 'user', 'yacht', 'column']);
    }
}
