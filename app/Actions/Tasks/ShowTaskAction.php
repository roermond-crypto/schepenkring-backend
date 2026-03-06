<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;

class ShowTaskAction
{
    public function __construct(private TaskRepository $tasks)
    {
    }

    public function execute(User $user, int $id): Task
    {
        $task = $this->tasks->findForUserOrFail($id, $user);

        if ($task->assigned_to === $user->id && $task->status === 'New') {
            $task->update(['status' => 'Pending']);
        }

        return $task->fresh(['assignedTo:id,name,email', 'creator:id,name,email', 'user:id,name,email', 'yacht:id,name', 'column']);
    }
}
