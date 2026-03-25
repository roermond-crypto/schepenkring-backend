<?php

namespace App\Repositories;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskAccessService;
use Illuminate\Database\Eloquent\Builder;

class TaskRepository
{
    public function __construct(private TaskAccessService $access)
    {
    }

    public function baseQuery(): Builder
    {
        return Task::query()->with([
            'assignedTo:id,name,email',
            'creator:id,name,email',
            'user:id,name,email',
            'yacht:id,boat_name',
            'automation.relatedYacht:id,boat_name',
            'column',
        ]);
    }

    public function queryForUser(User $user): Builder
    {
        return $this->access->scopeTasksForUser($this->baseQuery(), $user);
    }

    public function findForUserOrFail(int $id, User $user): Task
    {
        return $this->queryForUser($user)->where('tasks.id', $id)->firstOrFail();
    }

    public function create(array $data): Task
    {
        return Task::create($data);
    }

    public function update(Task $task, array $data): Task
    {
        $task->fill($data);
        $task->save();

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }
}
