<?php

namespace App\Actions\Me;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdatePasswordAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $user, array $data, ?string $idempotencyKey): User
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Invalid password.',
            ]);
        }

        $this->security->requireIdempotency($idempotencyKey, 'me.password.update', $user);
        $before = $user->toArray();

        $updated = $this->users->update($user, [
            'password' => $data['password'],
            'password_changed_at' => now(),
        ]);

        $this->security->log('me.password.update', RiskLevel::HIGH, $user, $updated, [], [
            'location_id' => $user->client_location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
