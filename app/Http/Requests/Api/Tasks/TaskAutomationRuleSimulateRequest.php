<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskAutomationRuleSimulateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'trigger_event' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', 'string', 'max:255'],
            'entity_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
