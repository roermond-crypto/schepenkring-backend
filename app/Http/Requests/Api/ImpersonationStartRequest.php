<?php

namespace App\Http\Requests\Api;

class ImpersonationStartRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'otp_code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
