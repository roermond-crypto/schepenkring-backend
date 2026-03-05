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
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class CreateTaskAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskActivityLogRepository $activityLogs,
        private LocationAccessService $locationAccess,
        private TaskAccessService $taskAccess,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, array $data): Task
    {
        $assignee = null;
        if (! empty($data['assigned_to'])) {
            $assignee = User::find($data['assigned_to']);
        }

        $locationId = $this->resolveLocationId($actor, $assignee, $data);

        if ($actor->isEmployee()) {
            if (! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        if ($actor->isClient()) {
            if ($actor->client_location_id !== $locationId) {
                throw new AuthorizationException('Unauthorized');
            }
            if (($data['type'] ?? 'assigned') === 'assigned' && ($assignee?->id ?? null) !== $actor->id) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        if ($assignee && $assignee->isEmployee() && $actor->isEmployee()) {
            if (! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }

            if (! $this->taskAccess->canAssign($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        $payload = $data;
        $payload['status'] = $payload['status'] ?? 'New';
        $payload['created_by'] = $actor->id;
        $payload['location_id'] = $locationId;
        $payload['client_visible'] = $actor->isClient() ? true : ($payload['client_visible'] ?? false);

        if (($payload['type'] ?? 'assigned') === 'personal') {
            $payload['user_id'] = $actor->id;
            $payload['assigned_to'] = $actor->id;
            $payload['assignment_status'] = 'accepted';
        } else {
            $payload['assignment_status'] = 'pending';
            $payload['user_id'] = null;
            $payload['assigned_to'] = $payload['assigned_to'] ?? null;
        }

        $task = $this->tasks->create($payload);

        $this->activityLogs->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => 'created',
            'description' => 'Task created',
            'location_id' => $task->location_id,
        ]);

        $this->security->log('task.create', RiskLevel::LOW, $actor, $task, [
            'status' => $task->status,
            'assigned_to' => $task->assigned_to,
            'type' => $task->type,
            'client_visible' => $task->client_visible,
        ], [
            'location_id' => $task->location_id,
            'snapshot_after' => $task->toArray(),
        ]);

        return $task->load(['assignedTo', 'creator', 'user', 'yacht', 'column']);
    }

    private function resolveLocationId(User $actor, ?User $assignee, array $data): ?int
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

        if ($assignee) {
            if ($assignee->isClient()) {
                return $assignee->client_location_id;
            }

            if ($assignee->isEmployee()) {
                return $assignee->locations()->value('locations.id');
            }
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
