<?php

namespace App\Http\Requests\Api;

use App\Enums\LocationRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use Illuminate\Validation\Rule;

class AdminUserStoreRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                UserType::EMPLOYEE->value,
                UserType::CLIENT->value,
            ])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['nullable', Rule::in(array_map(fn (UserStatus $status) => $status->value, UserStatus::cases()))],
            'location_id' => [
                Rule::requiredIf(fn () => $this->input('type') === UserType::CLIENT->value),
                'nullable',
                'integer',
                'exists:locations,id',
            ],
            'location_role' => [
                'nullable',
                Rule::in(array_map(fn (LocationRole $role) => $role->value, LocationRole::cases())),
            ],
        ];
    }
}
