<?php

namespace App\Repositories;

use App\Models\TaskActivityLog;

class TaskActivityLogRepository
{
    public function create(array $data): TaskActivityLog
    {
        return TaskActivityLog::create($data);
    }
}
