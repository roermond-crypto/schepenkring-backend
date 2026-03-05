<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskActivityLogRepository;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class AddTaskCommentAction
{
    public function __construct(
        private TaskActivityLogRepository $activityLogs,
        private TaskAccessService $access,
        private ActionSecurity $security,
        private NotificationDispatchService $notifications
    ) {
    }

    public function execute(User $actor, Task $task, string $content)
    {
        if (! $this->access->canView($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $log = $this->activityLogs->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => 'commented',
            'description' => $content,
            'location_id' => $task->location_id,
        ])->load('user:id,name,email');

        $this->security->log('task.comment.add', RiskLevel::LOW, $actor, $task, [
            'comment_id' => $log->id,
        ], [
            'location_id' => $task->location_id,
        ]);

        $recipientId = $task->assigned_to ?? $task->created_by ?? null;
        if ($recipientId && $recipientId !== $actor->id) {
            $recipient = User::find($recipientId);
            if ($recipient) {
                $this->notifications->notifyUser(
                    $recipient,
                    'info',
                    'New task comment',
                    "{$actor->name} commented on {$task->title}",
                    [
                        'task_id' => $task->id,
                        'entity_type' => 'task',
                        'entity_id' => $task->id,
                        'url' => "/dashboard/tasks/{$task->id}",
                    ],
                    null,
                    true,
                    true,
                    $task->location_id
                );
            }
        }

        return $log;
    }
}
