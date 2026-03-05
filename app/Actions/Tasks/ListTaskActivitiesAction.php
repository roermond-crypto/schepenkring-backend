<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class ListTaskActivitiesAction
{
    public function __construct(private TaskAccessService $access)
    {
    }

    public function execute(User $actor, Task $task): array
    {
        if (! $this->access->canView($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $activities = $task->activityLogs()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        $attachments = $task->attachments()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'activities' => $activities,
            'attachments' => $attachments,
        ];
    }
}
