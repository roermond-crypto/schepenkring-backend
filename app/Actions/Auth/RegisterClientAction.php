<?php

namespace App\Actions\Auth;

use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;

class RegisterClientAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    )
    {
    }

    public function execute(array $data): User
    {
        $user = $this->users->create([
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

        $this->security->log('auth.register', RiskLevel::LOW, $user, $user, [
            'source' => 'self_signup',
        ], [
            'location_id' => $user->client_location_id,
            'snapshot_after' => $user->toArray(),
        ]);

        return $user;
    }
}
