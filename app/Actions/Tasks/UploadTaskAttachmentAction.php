<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskActivityLogRepository;
use App\Repositories\TaskAttachmentRepository;
use App\Services\TaskAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadTaskAttachmentAction
{
    public function __construct(
        private TaskAttachmentRepository $attachments,
        private TaskActivityLogRepository $activityLogs,
        private TaskAccessService $access
    ) {
    }

    public function execute(User $actor, Task $task, UploadedFile $file)
    {
        if (! $this->access->canEdit($actor, $task)) {
            throw new AuthorizationException('Unauthorized');
        }

        $path = $file->store('task_attachments', 'public');

        $attachment = $this->attachments->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'location_id' => $task->location_id,
        ]);

        $this->activityLogs->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'action' => 'attachment_added',
            'description' => 'Uploaded file: '.$file->getClientOriginalName(),
            'location_id' => $task->location_id,
        ]);

        return $attachment->load('user:id,name');
    }
}
