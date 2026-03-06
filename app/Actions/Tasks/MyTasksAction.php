<?php

namespace App\Actions\Tasks;

use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Support\Collection;

class MyTasksAction
{
    public function __construct(private TaskRepository $tasks)
    {
    }

    public function execute(User $user): Collection
    {
        return $this->tasks->queryForUser($user)
            ->where(function ($query) use ($user) {
                $query->where('assigned_to', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
