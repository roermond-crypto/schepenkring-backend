<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'in:Low,Medium,High,Urgent,Critical'],
            'status' => ['sometimes', 'in:New,Pending,To Do,In Progress,Done'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'yacht_id' => ['nullable', 'integer', 'exists:boats,id'],
            'appointment_id' => ['nullable', 'integer'],
            'due_date' => ['sometimes', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'column_id' => ['nullable', 'integer', 'exists:columns,id'],
            'position' => ['sometimes', 'integer'],
            'type' => ['sometimes', 'in:personal,assigned'],
            'client_visible' => ['nullable', 'boolean'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }
}
