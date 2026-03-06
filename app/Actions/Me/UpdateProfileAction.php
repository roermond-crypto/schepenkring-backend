<?php

namespace App\Actions\Me;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use Illuminate\Support\Arr;

class UpdateProfileAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    )
    {
    }

    public function execute(User $user, array $data): User
    {
        $payload = Arr::only($data, ['name', 'timezone', 'locale', 'notifications_enabled', 'email_notifications_enabled']);

        $before = $user->toArray();
        $updated = $this->users->update($user, $payload);

        $this->security->log('me.profile.update', RiskLevel::LOW, $user, $updated, [], [
            'location_id' => $user->client_location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
