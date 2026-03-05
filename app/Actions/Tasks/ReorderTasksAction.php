<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Task;
use App\Models\User;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReorderTasksAction
{
    public function __construct(
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, array $tasks): void
    {
        if ($actor->isAdmin()) {
            $this->applyUpdates($tasks);
            $this->logReorder($actor, $tasks);
            return;
        }

        if (! $actor->isEmployee()) {
            throw new AuthorizationException('Unauthorized');
        }

        $locationIds = $this->locationAccess->accessibleLocationIds($actor);

        foreach ($tasks as $taskData) {
            $task = Task::find($taskData['id']);
            if (! $task || ! in_array($task->location_id, $locationIds, true)) {
                throw new AuthorizationException('Unauthorized');
            }

            if (! $this->permissions->hasLocationPermission($actor, 'tasks.manage', $task->location_id)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        $this->applyUpdates($tasks);
        $this->logReorder($actor, $tasks);
    }

    private function applyUpdates(array $tasks): void
    {
        DB::transaction(function () use ($tasks) {
            foreach ($tasks as $taskData) {
                Task::where('id', $taskData['id'])->update([
                    'position' => $taskData['position'],
                    'column_id' => $taskData['column_id'],
                ]);
            }
        });
    }

    private function logReorder(User $actor, array $tasks): void
    {
        $firstTaskId = $tasks[0]['id'] ?? null;
        $firstTask = $firstTaskId ? Task::find($firstTaskId) : null;

        $this->security->log('task.reorder', RiskLevel::LOW, $actor, $firstTask, [
            'tasks' => $tasks,
        ], [
            'location_id' => $firstTask?->location_id,
        ]);
    }
}
