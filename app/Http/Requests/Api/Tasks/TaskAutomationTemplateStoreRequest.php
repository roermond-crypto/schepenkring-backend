<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskAutomationTemplateStoreRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'trigger_event' => ['required', 'string', 'max:255'],
            'schedule_type' => ['required', 'in:relative,fixed,recurring,specific_date'],
            'delay_value' => ['required_if:schedule_type,relative', 'integer', 'min:1'],
            'delay_unit' => ['required_if:schedule_type,relative', 'in:minutes,hours,days,weeks'],
            'fixed_at' => ['required_if:schedule_type,fixed', 'date'],
            'cron_expression' => ['required_if:schedule_type,recurring', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:Low,Medium,High,Urgent,Critical'],
            'default_assignee_type' => ['required', 'in:admin,seller,buyer,harbor,creator,related_owner,specific_user'],
            'notification_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'boat_type_filter' => ['nullable', 'array'],
            'boat_type_filter.*' => ['string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.title' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.priority' => ['nullable', 'in:Low,Medium,High'],
            'items.*.position' => ['nullable', 'integer', 'min:0'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }
}
