<?php

namespace App\Actions\User;

use App\Enums\RiskLevel;
use App\Enums\UserType;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;
use Illuminate\Validation\ValidationException;

class AssignUserLocationsAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security,
        private NotificationDispatchService $notifications
    ) {
    }

    public function execute(User $target, array $data, User $actor, ?string $idempotencyKey): User
    {
        $this->security->requireIdempotency($idempotencyKey, 'admin.user.locations', $actor);
        $before = $target->toArray();

        if ($target->type === UserType::CLIENT) {
            if (empty($data['location_id'])) {
                throw ValidationException::withMessages([
                    'location_id' => 'Client users must belong to a location.',
                ]);
            }

            $this->users->update($target, [
                'client_location_id' => $data['location_id'],
            ]);

            $target->locations()->detach();

            $this->security->log('admin.user.locations', RiskLevel::HIGH, $actor, $target, [
                'client_location_id' => $data['location_id'],
            ], [
                'location_id' => $data['location_id'],
                'snapshot_before' => $before,
                'snapshot_after' => $target->fresh()->toArray(),
            ]);

            $this->notifications->notifyUser(
                $target,
                'system',
                'Location assignment updated',
                'Your location assignment has been updated.',
                [
                    'entity_type' => 'user',
                    'entity_id' => $target->id,
                    'location_id' => $data['location_id'],
                    'url' => '/dashboard/account',
                ],
                null,
                true,
                true,
                $data['location_id']
            );

            return $target;
        }

        if ($target->type !== UserType::EMPLOYEE) {
            throw ValidationException::withMessages([
                'type' => 'Location assignments are only supported for employees and clients.',
            ]);
        }

        if (empty($data['locations'])) {
            throw ValidationException::withMessages([
                'locations' => 'Employee users must have at least one location assignment.',
            ]);
        }

        $this->users->syncLocations($target, $data['locations']);

        $this->security->log('admin.user.locations', RiskLevel::HIGH, $actor, $target, [
            'locations' => $data['locations'],
        ], [
            'snapshot_before' => $before,
            'snapshot_after' => $target->fresh()->toArray(),
        ]);

        $locationId = $data['locations'][0]['location_id'] ?? null;
        $this->notifications->notifyUser(
            $target,
            'system',
            'Location assignment updated',
            'Your location assignments have been updated.',
            [
                'entity_type' => 'user',
                'entity_id' => $target->id,
                'locations' => $data['locations'],
                'url' => '/dashboard/account',
            ],
            null,
            true,
            true,
            $locationId
        );

        return $target->refresh();
    }
}
