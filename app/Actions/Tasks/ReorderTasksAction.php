<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReorderTasksAction
{
    public function __construct(
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, array $tasks): void
    {
        if ($actor->isAdmin()) {
            $this->applyUpdates($tasks);
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
}
