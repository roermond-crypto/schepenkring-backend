<?php

namespace App\Actions\Tasks;

use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Support\Collection;

class ListTasksAction
{
    public function __construct(private TaskRepository $tasks)
    {
    }

    public function execute(User $user): Collection
    {
        return $this->tasks->queryForUser($user)
            ->latest()
            ->get();
    }
}
