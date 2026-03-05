<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskAutomationStoreRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'template_id' => ['required', 'exists:task_automation_templates,id'],
            'trigger_event' => ['nullable', 'string', 'max:255'],
            'related_type' => ['nullable', 'string', 'max:255'],
            'related_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'base_at' => ['nullable', 'date'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }
}
