<?php

namespace App\Http\Requests\Api;

class MeAddressRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'address_line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:2'],
        ];
    }
}
