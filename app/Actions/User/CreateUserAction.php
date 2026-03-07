<?php

namespace App\Actions\User;

use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use Illuminate\Validation\ValidationException;

class CreateUserAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    )
    {
    }

    public function execute(array $data, User $actor, ?string $idempotencyKey = null): User
    {
        $type = UserType::from($data['type']);

        if ($type === UserType::ADMIN) {
            throw ValidationException::withMessages([
                'type' => 'Admin users must be created by internal tooling.',
            ]);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'type' => $type,
            'status' => UserStatus::from($data['status'] ?? UserStatus::ACTIVE->value),
            'email_changed_at' => now(),
            'phone_changed_at' => array_key_exists('phone', $data) ? now() : null,
            'password_changed_at' => now(),
        ];

        foreach ([
            'first_name',
            'last_name',
            'date_of_birth',
            'timezone',
            'locale',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if ($type === UserType::CLIENT) {
            if (empty($data['location_id'])) {
                throw ValidationException::withMessages([
                    'location_id' => 'Client users must belong to a location.',
                ]);
            }
            $payload['client_location_id'] = $data['location_id'];
        }

        $user = $this->users->create($payload);

        $this->security->log('admin.user.create', RiskLevel::MEDIUM, $actor, $user, [
            'type' => $type->value,
        ], [
            'location_id' => $user->client_location_id,
            'snapshot_after' => $user->toArray(),
            'idempotency_key' => $idempotencyKey,
        ]);

        return $user;
    }
}
