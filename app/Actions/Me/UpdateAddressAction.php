<?php

namespace App\Actions\Me;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use Illuminate\Support\Arr;

class UpdateAddressAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    )
    {
    }

    public function execute(User $user, array $data): User
    {
        $payload = Arr::only($data, [
            'street',
            'house_number',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
        ]);

        // Keep address_line1 in sync with street for backward compat
        if (isset($payload['street'])) {
            $payload['address_line1'] = $payload['street'];
        }
        if (isset($payload['house_number'])) {
            $payload['address_line2'] = $payload['house_number'];
        }

        $before = $user->toArray();
        $updated = $this->users->update($user, $payload);

        $this->security->log('me.address.update', RiskLevel::LOW, $user, $updated, [], [
            'location_id' => $user->client_location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
