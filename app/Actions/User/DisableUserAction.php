<?php

namespace App\Actions\User;

use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;

class DisableUserAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security,
        private NotificationDispatchService $notifications
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

        $this->notifications->notifyUser(
            $user,
            'warning',
            'Account disabled',
            'Your account has been disabled. Please contact support.',
            [
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'status' => $user->status?->value ?? $user->status,
                'url' => '/dashboard/account',
            ],
            null,
            true,
            true,
            $user->client_location_id
        );

        return $user;
    }
}
