<?php

namespace App\Http\Requests\Api;

use App\Enums\UserStatus;
use Illuminate\Validation\Rule;

class AdminUserUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:25'],
            'status' => ['sometimes', Rule::in(array_map(fn (UserStatus $status) => $status->value, UserStatus::cases()))],
            'type' => ['prohibited'],
            'client_location_id' => ['prohibited'],
            'locations' => ['prohibited'],
        ];
    }
}
