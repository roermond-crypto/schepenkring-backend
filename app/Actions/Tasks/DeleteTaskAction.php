<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteTaskAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskAccessService $access
    ) {
    }

    public function execute(User $actor, Task $task): void
    {
        if (! $this->access->canDelete($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $task->activityLogs()->delete();
        $this->tasks->delete($task);
    }
}
