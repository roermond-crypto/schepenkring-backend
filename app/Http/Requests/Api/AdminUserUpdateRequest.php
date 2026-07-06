<?php

namespace App\Http\Requests\Api;

use App\Enums\LocationRole;
use App\Enums\UserStatus;
use Illuminate\Validation\Rule;

class AdminUserUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            // Identity
            'name'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'first_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'email'         => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:25'],
            'status'        => ['sometimes', Rule::in(array_map(fn (UserStatus $status) => $status->value, UserStatus::cases()))],

            // Address
            'street'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'house_number'  => ['sometimes', 'nullable', 'string', 'max:50'],
            'address_line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'state'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code'   => ['sometimes', 'nullable', 'string', 'max:20'],
            'country'       => ['sometimes', 'nullable', 'string', 'max:255'],

            // Security
            'two_factor_enabled'    => ['sometimes', 'boolean'],
            'password'              => ['sometimes', 'nullable', 'string', 'min:8'],
            'password_confirmation' => ['sometimes', 'nullable', 'string', 'same:password'],

            // Location assignment
            'type'               => ['prohibited'],
            'client_location_id' => ['prohibited'],
            'locations'          => ['prohibited'],
            'location_id'        => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'location_role'      => [
                'sometimes',
                'nullable',
                Rule::in(array_map(fn (LocationRole $role) => $role->value, LocationRole::cases())),
            ],
        ];
    }
}
