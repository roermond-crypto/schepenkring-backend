<?php

namespace App\Repositories;

use App\Models\TaskAttachment;

class TaskAttachmentRepository
{
    public function create(array $data): TaskAttachment
    {
        return TaskAttachment::create($data);
    }
}
