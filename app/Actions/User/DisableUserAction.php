<?php

namespace App\Actions\User;

use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;

class DisableUserAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $target, User $actor, ?string $idempotencyKey): User
    {
        $this->security->requireIdempotency($idempotencyKey, 'admin.user.disable', $actor);
        $before = $target->toArray();

        $user = $this->users->update($target, [
            'status' => UserStatus::DISABLED,
        ]);

        $this->users->revokeTokens($user);

        $this->security->log('admin.user.disable', RiskLevel::HIGH, $actor, $user, [], [
            'location_id' => $user->client_location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $user->toArray(),
        ]);

        return $user;
    }
}
