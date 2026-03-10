<?php

namespace App\Http\Requests\Api\Signhost;

use App\Http\Requests\Api\ApiRequest;

class SignhostRequestActionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'sign_request_id' => ['nullable', 'integer', 'exists:sign_requests,id'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'password' => ['nullable', 'string'],
            'otp_code' => ['nullable', 'string', 'max:10'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
