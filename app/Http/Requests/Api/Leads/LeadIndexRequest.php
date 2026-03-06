<?php

namespace App\Http\Requests\Api\Leads;

use App\Http\Requests\Api\ApiRequest;
use Illuminate\Validation\Rule;

class LeadIndexRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['new', 'contacted', 'interested', 'converted', 'closed'])],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'assigned_employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
