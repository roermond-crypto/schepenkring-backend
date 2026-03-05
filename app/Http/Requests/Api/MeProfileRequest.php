<?php

namespace App\Http\Requests\Api;

class MeProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'nullable', 'timezone'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
