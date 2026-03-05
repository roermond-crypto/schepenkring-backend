<?php

namespace App\Http\Requests\Api\Signhost;

use App\Http\Requests\Api\ApiRequest;

class SignhostRequestQuery extends ApiRequest
{
    public function rules(): array
    {
        return [
            'sign_request_id' => ['nullable', 'integer', 'exists:sign_requests,id'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
