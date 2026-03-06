<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskStoreRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:Low,Medium,High,Urgent,Critical'],
            'status' => ['nullable', 'in:New,Pending,To Do,In Progress,Done'],
            'due_date' => ['required', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'type' => ['required', 'in:personal,assigned'],
            'assigned_to' => ['required_if:type,assigned', 'nullable', 'integer', 'exists:users,id'],
            'yacht_id' => ['nullable', 'integer', 'exists:boats,id'],
            'appointment_id' => ['nullable', 'integer'],
            'column_id' => ['nullable', 'integer', 'exists:columns,id'],
            'position' => ['nullable', 'integer'],
            'client_visible' => ['nullable', 'boolean'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }
}
