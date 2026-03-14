<?php

namespace App\Actions\User;

use App\Enums\LocationRole;
use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        $target->loadMissing(['locations', 'clientLocation']);
        $before = $target->toArray();

        $emailChanged = array_key_exists('email', $allowed) && $allowed['email'] !== $target->email;
        $phoneChanged = array_key_exists('phone', $allowed) && $allowed['phone'] !== $target->phone;
        $statusChanged = array_key_exists('status', $allowed) && $allowed['status'] !== $target->status?->value;
        $locationChanged = $this->locationAssignmentWillChange($target, $data);

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

        $sensitiveChange = $emailChanged || $phoneChanged || $statusChanged || $locationChanged;

        if ($sensitiveChange) {
            $this->security->requireIdempotency($idempotencyKey, 'admin.user.update', $actor);
        }

        $user = DB::transaction(function () use ($target, $allowed, $data) {
            $user = $this->users->update($target, $allowed);
            $this->syncLocationAssignment($user, $data);

            return $user->refresh()->load(['locations', 'clientLocation']);
        });

        if ($statusChanged && $user->status !== UserStatus::ACTIVE) {
            $this->users->revokeTokens($user);
        }

        $this->security->log('admin.user.update', $sensitiveChange ? RiskLevel::HIGH : RiskLevel::LOW, $actor, $user, [
            'email_changed' => $emailChanged,
            'phone_changed' => $phoneChanged,
            'status_changed' => $statusChanged,
            'location_changed' => $locationChanged,
        ], [
            'location_id' => $user->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $user->toArray(),
        ]);

        return $user;
    }

    private function locationAssignmentWillChange(User $target, array $data): bool
    {
        if (! array_key_exists('location_id', $data) && ! array_key_exists('location_role', $data)) {
            return false;
        }

        if ($target->type === UserType::CLIENT) {
            return array_key_exists('location_id', $data) && $target->client_location_id !== ($data['location_id'] ?? null);
        }

        if ($target->type !== UserType::EMPLOYEE) {
            return false;
        }

        $nextLocationId = array_key_exists('location_id', $data)
            ? (! empty($data['location_id']) ? (int) $data['location_id'] : null)
            : $target->location_id;
        $nextRole = array_key_exists('location_role', $data)
            ? $data['location_role']
            : ($target->location_role ?? LocationRole::LOCATION_EMPLOYEE->value);

        return $target->location_id !== $nextLocationId || $target->location_role !== $nextRole;
    }

    private function syncLocationAssignment(User $target, array $data): void
    {
        if (! array_key_exists('location_id', $data) && ! array_key_exists('location_role', $data)) {
            return;
        }

        if ($target->type === UserType::CLIENT) {
            if (empty($data['location_id'])) {
                throw ValidationException::withMessages([
                    'location_id' => 'Client users must belong to a location.',
                ]);
            }

            $this->users->update($target, [
                'client_location_id' => (int) $data['location_id'],
            ]);
            $target->locations()->detach();

            return;
        }

        if ($target->type !== UserType::EMPLOYEE) {
            return;
        }

        $locationId = array_key_exists('location_id', $data)
            ? (! empty($data['location_id']) ? (int) $data['location_id'] : null)
            : $target->location_id;
        $locationRole = $data['location_role']
            ?? $target->location_role
            ?? LocationRole::LOCATION_EMPLOYEE->value;

        if (! $locationId) {
            $target->locations()->detach();

            return;
        }

        $this->users->syncLocations($target, [[
            'location_id' => $locationId,
            'role' => $locationRole,
        ]]);
    }
}
