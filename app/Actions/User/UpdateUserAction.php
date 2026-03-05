<?php

namespace App\Actions\User;

use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use Illuminate\Support\Arr;

class UpdateUserAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $target, array $data, User $actor, ?string $idempotencyKey): User
    {
        $allowed = Arr::only($data, ['name', 'email', 'phone', 'status']);

        $emailChanged = array_key_exists('email', $allowed) && $allowed['email'] !== $target->email;
        $phoneChanged = array_key_exists('phone', $allowed) && $allowed['phone'] !== $target->phone;
        $statusChanged = array_key_exists('status', $allowed) && $allowed['status'] !== $target->status?->value;

        if ($emailChanged) {
            $allowed['email_changed_at'] = now();
            $allowed['email_verified_at'] = null;
        }

        if ($phoneChanged) {
            $allowed['phone_changed_at'] = now();
        }

        if ($statusChanged) {
            $allowed['status'] = UserStatus::from($allowed['status']);
        }

        $sensitiveChange = $emailChanged || $phoneChanged || $statusChanged;

        if ($sensitiveChange) {
            $this->security->requireIdempotency($idempotencyKey, 'admin.user.update', $actor);
        }

        $user = $this->users->update($target, $allowed);

        if ($statusChanged && $user->status !== UserStatus::ACTIVE) {
            $this->users->revokeTokens($user);
        }

        if ($sensitiveChange) {
            $this->security->log('admin.user.update', RiskLevel::HIGH, $actor, $user, [
                'email_changed' => $emailChanged,
                'phone_changed' => $phoneChanged,
                'status_changed' => $statusChanged,
            ]);
        }

        return $user;
    }
}
