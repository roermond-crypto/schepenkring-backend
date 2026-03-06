<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Boat;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskActivityLogRepository;
use App\Repositories\TaskRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateTaskAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskActivityLogRepository $activityLogs,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private TaskAccessService $taskAccess,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, Task $task, array $data): Task
    {
        $before = $task->toArray();

        if (! $this->taskAccess->canEdit($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $reminderTouched = array_key_exists('reminder_at', $data);

        if (! $actor->isAdmin()
            && $actor->isEmployee()
            && ! $this->permissions->hasLocationPermission($actor, 'tasks.manage', $task->location_id)
            && $task->type === 'assigned') {
            $data = array_intersect_key($data, array_flip(['status']));
        }

        if (array_key_exists('type', $data)) {
            if ($data['type'] === 'personal') {
                $data['user_id'] = $actor->id;
                $data['assigned_to'] = $actor->id;
                $data['assignment_status'] = 'accepted';
            } else {
                $data['user_id'] = null;
                $newAssignee = $data['assigned_to'] ?? $task->assigned_to;
                if ($task->type === 'personal' || $task->assigned_to !== $newAssignee) {
                    $data['assignment_status'] = 'pending';
                    if (! isset($data['status'])) {
                        $data['status'] = 'New';
                    }
                }
            }
        }

        if (array_key_exists('assigned_to', $data)) {
            $assignee = $data['assigned_to'] ? User::find($data['assigned_to']) : null;
            if ($assignee && $assignee->isEmployee()) {
                $locationId = $this->resolveLocationId($actor, $assignee, $data, $task);
                if ($actor->isEmployee()) {
                    if (! $this->locationAccess->sharesLocation($actor, $locationId)) {
                        throw new AuthorizationException('Unauthorized');
                    }
                    if (! $this->taskAccess->canAssign($actor, $locationId)) {
                        throw new AuthorizationException('Unauthorized');
                    }
                }
                $data['location_id'] = $locationId;
            }

            if ($task->type === 'assigned' && $task->assigned_to !== $data['assigned_to']) {
                $data['assignment_status'] = 'pending';
                if (! isset($data['status'])) {
                    $data['status'] = 'New';
                }
            }
        }

        if (array_key_exists('location_id', $data) && $actor->isEmployee()) {
            if (! $this->locationAccess->sharesLocation($actor, $data['location_id'])) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        if ($reminderTouched) {
            $data['reminder_sent_at'] = null;
        }

        $this->tasks->update($task, $data);

        $this->activityLogs->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => 'updated',
            'description' => 'Task details updated',
            'location_id' => $task->location_id,
        ]);

        $updated = $task->fresh(['assignedTo', 'creator', 'user', 'yacht', 'column']);

        $this->security->log('task.update', RiskLevel::LOW, $actor, $task, [
            'fields' => array_keys($data),
        ], [
            'location_id' => $updated->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }

    private function resolveLocationId(User $actor, ?User $assignee, array $data, Task $task): ?int
    {
        if (! empty($data['yacht_id'])) {
            $boat = Boat::find($data['yacht_id']);
            if ($boat) {
                return $boat->location_id;
            }
        }

        if (! empty($data['location_id'])) {
            return (int) $data['location_id'];
        }

        if ($assignee && $assignee->isClient()) {
            return $assignee->client_location_id;
        }

        if ($assignee && $assignee->isEmployee()) {
            return $assignee->locations()->value('locations.id');
        }

        if ($task->location_id) {
            return $task->location_id;
        }

        if ($actor->isClient()) {
            return $actor->client_location_id;
        }

        if ($actor->isEmployee()) {
            return $actor->locations()->value('locations.id');
        }

        return null;
    }
}
