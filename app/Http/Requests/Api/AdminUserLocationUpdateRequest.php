<?php

namespace App\Http\Requests\Api;

use App\Enums\LocationRole;
use Illuminate\Validation\Rule;

class AdminUserLocationUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_role' => [
                'nullable',
                Rule::in(array_map(fn (LocationRole $role) => $role->value, LocationRole::cases())),
            ],
            'locations' => ['nullable', 'array', 'max:20'],
            'locations.*.location_id' => ['required_with:locations', 'integer', 'exists:locations,id'],
            'locations.*.role' => [
                'required_with:locations',
                Rule::in(array_map(fn (LocationRole $role) => $role->value, LocationRole::cases())),
            ],
        ];
    }
}
