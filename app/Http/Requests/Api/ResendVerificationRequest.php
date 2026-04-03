<?php

namespace App\Http\Requests\Api;

class ResendVerificationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:5'],
        ];
    }
}
