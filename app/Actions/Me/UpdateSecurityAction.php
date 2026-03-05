<?php

namespace App\Actions\Me;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use App\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateSecurityAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $user, array $data, ?string $idempotencyKey): User
    {
        $enable = (bool) $data['two_factor_enabled'];

        if ($enable) {
            if (! Totp::verify($data['otp_secret'], $data['otp_code'])) {
                throw ValidationException::withMessages([
                    'otp_code' => 'Invalid OTP code.',
                ]);
            }

            $payload = [
                'two_factor_enabled' => true,
                'otp_secret' => $data['otp_secret'],
                'two_factor_confirmed_at' => now(),
            ];
        } else {
            if (! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'Invalid password.',
                ]);
            }

            $payload = [
                'two_factor_enabled' => false,
                'otp_secret' => null,
                'two_factor_confirmed_at' => null,
            ];
        }

        $this->security->requireIdempotency($idempotencyKey, 'me.security.update', $user);

        $updated = $this->users->update($user, $payload);

        $this->security->log('me.security.update', RiskLevel::HIGH, $user, $updated, [
            'enabled' => $enable,
        ]);

        return $updated;
    }
}
