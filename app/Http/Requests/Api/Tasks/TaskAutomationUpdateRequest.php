<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskAutomationUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'due_at' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:pending,processing,completed,failed,canceled'],
            'assigned_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }
}
