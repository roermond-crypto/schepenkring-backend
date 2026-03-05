<?php

namespace App\Actions\Me;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use Illuminate\Support\Arr;

class UpdatePersonalAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $user, array $data, ?string $idempotencyKey): User
    {
        $payload = Arr::only($data, ['first_name', 'last_name', 'phone', 'date_of_birth', 'email']);

        $emailChanged = array_key_exists('email', $payload) && $payload['email'] !== $user->email;
        $phoneChanged = array_key_exists('phone', $payload) && $payload['phone'] !== $user->phone;

        if ($emailChanged) {
            $payload['email_changed_at'] = now();
            $payload['email_verified_at'] = null;
        }

        if ($phoneChanged) {
            $payload['phone_changed_at'] = now();
        }

        if ($emailChanged || $phoneChanged) {
            $this->security->requireIdempotency($idempotencyKey, 'me.personal.update', $user);
        }

        $updated = $this->users->update($user, $payload);

        if ($emailChanged || $phoneChanged) {
            $this->security->log('me.personal.update', RiskLevel::HIGH, $user, $updated, [
                'email_changed' => $emailChanged,
                'phone_changed' => $phoneChanged,
            ]);
        }

        return $updated;
    }
}
