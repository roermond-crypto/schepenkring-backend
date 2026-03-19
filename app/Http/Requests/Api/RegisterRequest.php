<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            // Must explicitly accept terms & conditions before registering.
            'terms_accepted' => ['required', 'accepted'],
            'website' => ['nullable', 'max:0'],
            'type' => ['prohibited'],
            'status' => ['prohibited'],
            'role' => ['prohibited'],
            'roles' => ['prohibited'],
            'permissions' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'terms_accepted.required' => 'You must accept the terms and conditions to register.',
            'terms_accepted.accepted'  => 'You must accept the terms and conditions to register.',
        ];
    }
}
