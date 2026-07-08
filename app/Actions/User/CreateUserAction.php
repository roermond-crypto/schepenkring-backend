<?php

namespace App\Actions\User;

use App\Enums\LocationRole;
use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Mail\AdminClientOnboardingMail;
use App\Models\BuyerProfile;
use App\Models\BuyerVerification;
use App\Models\SellerOnboarding;
use App\Models\SellerProfile;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use App\Support\BuyerVerificationStatus;
use App\Support\SellerOnboardingStatus;
use Illuminate\Support\Facades\Mail;
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

        // PARTNER: location-pivot user, like EMPLOYEE but with different type
        if ($type === UserType::PARTNER) {
            return $this->createPartnerUser($data, $actor, $idempotencyKey);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'type' => $type,
            'status' => UserStatus::from($data['status'] ?? UserStatus::ACTIVE->value),
            'email_verified_at' => now(),
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

        if (in_array($type, [UserType::CLIENT, UserType::BUYER, UserType::SELLER], true)) {
            if (empty($data['location_id'])) {
                throw ValidationException::withMessages([
                    'location_id' => 'Client users must belong to a location.',
                ]);
            }
            $payload['client_location_id'] = $data['location_id'];
        }

        $user = $this->users->create($payload);

        if (in_array($type, [UserType::BUYER, UserType::SELLER], true)) {
            $needsOnboarding = filter_var($data['needs_onboarding'] ?? true, FILTER_VALIDATE_BOOL);
            $this->createClientOnboardingRecords($user, $type, $needsOnboarding);

            if ($needsOnboarding) {
                Mail::to($user->email)->send(new AdminClientOnboardingMail(
                    $user,
                    (string) $data['password'],
                    $type === UserType::SELLER ? 'seller' : 'buyer',
                    $user->locale
                ));
            }
        }

        if ($type === UserType::EMPLOYEE && ! empty($data['location_id'])) {
            $this->users->syncLocations($user, [[
                'location_id' => (int) $data['location_id'],
                'role' => $data['location_role'] ?? LocationRole::LOCATION_EMPLOYEE->value,
            ]]);
            $user->load('locations');
        }

        $this->security->log('admin.user.create', RiskLevel::MEDIUM, $actor, $user, [
            'type' => $type->value,
        ], [
            'location_id' => $user->location_id,
            'snapshot_after' => $user->toArray(),
            'idempotency_key' => $idempotencyKey,
        ]);

        return $user;
    }

    private function createPartnerUser(array $data, User $actor, ?string $idempotencyKey): User
    {
        if (empty($data['location_id'])) {
            throw ValidationException::withMessages([
                'location_id' => 'Partner users must be assigned to a location.',
            ]);
        }

        $payload = [
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'phone'                => $data['phone'] ?? null,
            'password'             => $data['password'],
            'type'                 => UserType::PARTNER,
            'status'               => UserStatus::from($data['status'] ?? UserStatus::ACTIVE->value),
            'email_verified_at'    => now(),
            'email_changed_at'     => now(),
            'phone_changed_at'     => array_key_exists('phone', $data) ? now() : null,
            'password_changed_at'  => now(),
        ];

        foreach (['first_name', 'last_name', 'locale', 'timezone', 'address_line1', 'city', 'postal_code', 'country'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        $user = $this->users->create($payload);

        $this->users->syncLocations($user, [[
            'location_id' => (int) $data['location_id'],
            'role'        => $data['location_role'] ?? LocationRole::LOCATION_EMPLOYEE->value,
        ]]);

        $user->load('locations');

        $this->security->log('partner.created', RiskLevel::MEDIUM, $actor, $user, [
            'type'        => UserType::PARTNER->value,
            'location_id' => (int) $data['location_id'],
        ], [
            'location_id'     => (int) $data['location_id'],
            'snapshot_after'  => $user->toArray(),
            'idempotency_key' => $idempotencyKey,
        ]);

        return $user;
    }

    private function createClientOnboardingRecords(User $user, UserType $type, bool $needsOnboarding): void
    {
        $profileAttributes = [
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];

        if ($type === UserType::SELLER) {
            SellerProfile::query()->firstOrCreate(['user_id' => $user->id], $profileAttributes);
            SellerOnboarding::query()->firstOrCreate(
                ['user_id' => $user->id],
                $needsOnboarding
                    ? ['status' => SellerOnboardingStatus::CREATED]
                    : $this->approvedOnboardingAttributes(SellerOnboardingStatus::APPROVED, [
                        'payment_status' => 'APPROVED',
                        'idin_status' => 'APPROVED',
                        'ideal_status' => 'APPROVED',
                        'kyc_status' => 'APPROVED',
                        'contract_status' => 'APPROVED',
                        'can_publish_boat' => true,
                    ])
            );

            return;
        }

        BuyerProfile::query()->firstOrCreate(['user_id' => $user->id], $profileAttributes);
        BuyerVerification::query()->firstOrCreate(
            ['user_id' => $user->id],
            $needsOnboarding
                ? ['status' => BuyerVerificationStatus::CREATED]
                : $this->approvedOnboardingAttributes(BuyerVerificationStatus::APPROVED, [
                    'idin_status' => 'APPROVED',
                    'ideal_status' => 'APPROVED',
                    'kyc_status' => 'APPROVED',
                ])
        );
    }

    private function approvedOnboardingAttributes(string $status, array $extra = []): array
    {
        $now = now();

        return array_merge([
            'status' => $status,
            'decision' => 'approved',
            'decision_reason' => 'Approved directly by admin during client creation.',
            'manual_review_required' => false,
            'submitted_at' => $now,
            'approved_at' => $now,
            'verified_at' => $now,
            'expires_at' => $now->copy()->addYear(),
        ], $extra);
    }
}
