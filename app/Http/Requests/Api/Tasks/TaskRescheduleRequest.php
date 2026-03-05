<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskRescheduleRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'due_date' => ['required', 'date'],
            'reminder_at' => ['nullable', 'date'],
        ];
    }
}
