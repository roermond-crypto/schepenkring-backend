<?php

namespace App\Http\Requests\Api;

use App\Enums\UserStatus;
use App\Enums\UserType;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(array_map(fn (UserType $type) => $type->value, UserType::cases()))],
            'status' => ['nullable', Rule::in(array_map(fn (UserStatus $status) => $status->value, UserStatus::cases()))],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
