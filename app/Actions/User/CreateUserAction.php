<?php

namespace App\Actions\User;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Validation\ValidationException;

class CreateUserAction
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(array $data): User
    {
        $type = UserType::from($data['type']);

        if ($type === UserType::ADMIN) {
            throw ValidationException::withMessages([
                'type' => 'Admin users must be created by internal tooling.',
            ]);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'type' => $type,
            'status' => UserStatus::from($data['status'] ?? UserStatus::ACTIVE->value),
            'email_changed_at' => now(),
            'phone_changed_at' => array_key_exists('phone', $data) ? now() : null,
            'password_changed_at' => now(),
        ];

        if ($type === UserType::CLIENT) {
            if (empty($data['location_id'])) {
                throw ValidationException::withMessages([
                    'location_id' => 'Client users must belong to a location.',
                ]);
            }
            $payload['client_location_id'] = $data['location_id'];
        }

        return $this->users->create($payload);
    }
}
