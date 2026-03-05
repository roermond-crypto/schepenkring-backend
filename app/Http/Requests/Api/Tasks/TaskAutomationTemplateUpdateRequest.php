<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskAutomationTemplateUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'trigger_event' => ['sometimes', 'string', 'max:255'],
            'schedule_type' => ['sometimes', 'in:relative,fixed,recurring'],
            'delay_value' => ['required_if:schedule_type,relative', 'integer', 'min:1'],
            'delay_unit' => ['required_if:schedule_type,relative', 'in:minutes,hours,days,weeks'],
            'fixed_at' => ['required_if:schedule_type,fixed', 'date'],
            'cron_expression' => ['required_if:schedule_type,recurring', 'string', 'max:255'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'in:Low,Medium,High,Urgent,Critical'],
            'default_assignee_type' => ['sometimes', 'in:admin,seller,buyer,harbor,creator,related_owner'],
            'notification_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }
}
