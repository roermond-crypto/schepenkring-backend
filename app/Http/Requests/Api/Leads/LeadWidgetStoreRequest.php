<?php

namespace App\Http\Requests\Api\Leads;

use App\Http\Requests\Api\ApiRequest;
use Illuminate\Validation\Rule;

class LeadWidgetStoreRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'source_url' => ['required', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:2'],
            'message' => ['required', 'string', 'max:5000'],
            'client_message_id' => ['nullable', 'string', 'max:255'],
            'delivery_state' => ['nullable', Rule::in(['queued', 'sent', 'failed'])],
            'visitor_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
