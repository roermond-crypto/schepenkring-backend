<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class MePersonalRequest extends ApiRequest
{
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:25'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
        ];
    }
}
