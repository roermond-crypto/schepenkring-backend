<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class RescheduleTaskAction
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskAccessService $access
    ) {
    }

    public function execute(User $actor, Task $task, array $data): Task
    {
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

        return $task->fresh(['assignedTo', 'creator', 'user', 'yacht', 'column']);
    }
}
