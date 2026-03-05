<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locations = $this->whenLoaded('locations', function () {
            return $this->locations->map(function ($location) {
                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'code' => $location->code,
                    'role' => $location->pivot?->role,
                ];
            })->values();
        });

        $clientLocation = $this->whenLoaded('clientLocation', function () {
            return $this->clientLocation ? [
                'id' => $this->clientLocation->id,
                'name' => $this->clientLocation->name,
                'code' => $this->clientLocation->code,
            ] : null;
        });

        return [
            'id' => $this->id,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'date_of_birth' => $this->date_of_birth,
            'email' => $this->email,
            'phone' => $this->phone,
            'client_location_id' => $this->client_location_id,
            'client_location' => $clientLocation,
            'locations' => $locations,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'two_factor_enabled' => $this->two_factor_enabled,
            'two_factor_confirmed_at' => $this->two_factor_confirmed_at,
            'email_changed_at' => $this->email_changed_at,
            'phone_changed_at' => $this->phone_changed_at,
            'password_changed_at' => $this->password_changed_at,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
