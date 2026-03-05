<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use App\Services\ActionSecurity;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteTaskAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskAccessService $access,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, Task $task): void
    {
        if (! $this->access->canDelete($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $before = $task->toArray();

        $task->activityLogs()->delete();
        $this->tasks->delete($task);

        $this->security->log('task.delete', RiskLevel::LOW, $actor, $task, [], [
            'location_id' => $task->location_id,
            'snapshot_before' => $before,
        ]);
    }
}
