<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Repositories\TaskActivityLogRepository;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

class DeleteTaskAttachmentAction
{
    public function __construct(
        private TaskActivityLogRepository $activityLogs,
        private TaskAccessService $access
    ) {
    }

    public function execute(User $actor, Task $task, TaskAttachment $attachment): void
    {
        if (! $actor->isAdmin() && $attachment->user_id !== $actor->id) {
            throw new AuthorizationException('Unauthorized');
        }

        if (! $this->access->canView($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        $this->activityLogs->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => 'attachment_removed',
            'description' => 'Removed file: '.$attachment->file_name,
            'location_id' => $task->location_id,
        ]);
    }
}
