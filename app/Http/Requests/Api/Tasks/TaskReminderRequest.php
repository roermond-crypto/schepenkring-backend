<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskReminderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reminder_at' => ['nullable', 'date'],
        ];
    }
}
