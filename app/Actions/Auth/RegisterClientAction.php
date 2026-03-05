<?php

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Repositories\UserRepository;

class RegisterClientAction
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(array $data): User
    {
        return $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'type' => UserType::CLIENT,
            'status' => UserStatus::ACTIVE,
            'client_location_id' => $data['location_id'],
            'email_changed_at' => now(),
            'phone_changed_at' => array_key_exists('phone', $data) ? now() : null,
            'password_changed_at' => now(),
        ]);
    }
}
