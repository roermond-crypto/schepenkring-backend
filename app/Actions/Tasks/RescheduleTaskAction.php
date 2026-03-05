<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use App\Services\ActionSecurity;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class RescheduleTaskAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskAccessService $access,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, Task $task, array $data): Task
    {
        $before = $task->toArray();

        if (! $this->access->canEdit($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $payload = [
            'due_date' => $data['due_date'],
        ];

        if (array_key_exists('reminder_at', $data)) {
            $payload['reminder_at'] = $data['reminder_at'];
            $payload['reminder_sent_at'] = null;
        }

        $this->tasks->update($task, $payload);

        $updated = $task->fresh(['assignedTo', 'creator', 'user', 'yacht', 'column']);

        $this->security->log('task.reschedule', RiskLevel::LOW, $actor, $task, [
            'due_date' => $payload['due_date'],
            'reminder_at' => $payload['reminder_at'] ?? null,
        ], [
            'location_id' => $updated->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
