<?php

namespace App\Http\Requests\Api\Signhost;

use App\Http\Requests\Api\ApiRequest;

class SignhostRequestCreateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'sign_request_id' => ['nullable', 'integer', 'exists:sign_requests,id'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.email' => ['nullable', 'email'],
            'recipients.*.name' => ['nullable', 'string', 'max:255'],
            'recipients.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recipients.*.role' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'otp_code' => ['nullable', 'string', 'max:10'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
