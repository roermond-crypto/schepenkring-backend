<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskAutomationRuleStoreRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'internal_code' => ['nullable', 'string', 'max:255'],
            'trigger_event' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'target_role' => ['required', 'in:client,employee,admin'],
            'target_user_type' => ['nullable', 'required_if:target_role,client', 'in:buyer,seller'],
            'assignee_rule' => ['required', 'in:seller,creator,harbor_user,specific_user'],
            'assigned_user_id' => ['nullable', 'required_if:assignee_rule,specific_user', 'exists:users,id'],
            'boat_types' => ['nullable', 'array'],
            'boat_types.*' => ['string', 'max:255'],
            'boat_year_from' => ['nullable', 'integer', 'min:0'],
            'boat_year_to' => ['nullable', 'integer', 'min:0'],
            'location_filter' => ['nullable', 'string', 'max:255'],
            'visibility_delay_hours' => ['nullable', 'integer', 'min:0'],
            'visibility_status' => ['nullable', 'string', 'max:255'],
            'visibility_status_source' => ['nullable', 'in:related,boat,deal,bid,booking'],
            'actionable_delay_hours' => ['nullable', 'integer', 'min:0'],
            'actionable_status' => ['nullable', 'string', 'max:255'],
            'actionable_status_source' => ['nullable', 'in:related,boat,deal,bid,booking'],
            'actionable_requires_internal_tasks_completed' => ['sometimes', 'boolean'],
            'template_ids' => ['required', 'array', 'min:1'],
            'template_ids.*' => ['integer', 'exists:task_automation_templates,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }
}
