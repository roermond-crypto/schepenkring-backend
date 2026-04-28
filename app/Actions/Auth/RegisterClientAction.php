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
        $role = $data['role'] ?? 'buyer'; // Default to buyer if not specified
        $type = match ($role) {
            'seller' => UserType::SELLER,
            'buyer' => UserType::BUYER,
            default => UserType::CLIENT,
        };

        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'type' => $type,
            'status' => UserStatus::EMAIL_PENDING,
            'client_location_id' => $data['location_id'],
            'role' => $role,
            'email_changed_at' => now(),
            'phone_changed_at' => array_key_exists('phone', $data) ? now() : null,
            'password_changed_at' => now(),
        ]);

        // Create Profiles and Onboarding records based on role
        if ($role === 'seller') {
            \App\Models\SellerProfile::create([
                'user_id' => $user->id,
                'full_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ]);

            \App\Models\SellerOnboarding::create([
                'user_id' => $user->id,
                'status' => 'CREATED',
            ]);
        } else {
            \App\Models\BuyerProfile::create([
                'user_id' => $user->id,
                'full_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ]);

            \App\Models\BuyerVerification::create([
                'user_id' => $user->id,
                'status' => 'CREATED',
            ]);
        }

        $this->security->log('auth.register', RiskLevel::LOW, $user, $user, [
            'source' => 'self_signup',
            'role' => $role,
        ], [
            'location_id' => $user->client_location_id,
            'snapshot_after' => $user->toArray(),
        ]);

        return $user;
    }
}
