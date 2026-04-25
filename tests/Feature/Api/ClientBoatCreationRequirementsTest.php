<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use App\Services\BoatTaskAutomationService;
use App\Services\SyncYachtTasksService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app()->instance(SyncYachtTasksService::class, new class {
        public function syncForYacht(Yacht $yacht, ?User $actor = null): void
        {
        }
    });

    app()->instance(BoatTaskAutomationService::class, new class {
        public function fireForYacht(Yacht $yacht, User $actor, bool $isUpdate = false): array
        {
            return [];
        }
    });
});

test('client cannot create a boat when required customer details are missing and identity is unverified', function () {
    $location = Location::create([
        'name' => 'Client Validation Marina',
        'code' => 'CVM',
        'status' => 'ACTIVE',
    ]);

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
        'first_name' => null,
        'last_name' => null,
        'phone' => null,
        'date_of_birth' => null,
        'address_line1' => null,
        'city' => null,
        'postal_code' => null,
        'country' => null,
        'email_verified_at' => null,
    ]);

    Sanctum::actingAs($client);

    $response = $this->postJson('/api/yachts', [
        'boat_name' => 'Blocked Yacht',
        'status' => 'draft',
        'location_id' => $location->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error_code', 'client_profile_incomplete_for_boat_creation')
        ->assertJsonPath('requirements.identity_verification_required', true);

    $response->assertJsonFragment([
        'missing_profile_fields' => [
            'first_name',
            'last_name',
            'phone',
            'date_of_birth',
            'address_line1',
            'city',
            'postal_code',
            'country',
        ],
    ]);
});

test('client can create a boat after completing customer details and verification', function () {
    $location = Location::create([
        'name' => 'Eligible Client Marina',
        'code' => 'ECM',
        'status' => 'ACTIVE',
    ]);

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
        'first_name' => 'Sophie',
        'last_name' => 'de Boer',
        'phone' => '+31622223333',
        'date_of_birth' => '1992-02-20',
        'address_line1' => 'Havenstraat 4',
        'city' => 'Almere',
        'postal_code' => '1315AB',
        'country' => 'NL',
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($client);

    $response = $this->postJson('/api/yachts', [
        'boat_name' => 'Eligible Yacht',
        'status' => 'draft',
        'location_id' => $location->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('boat_name', 'Eligible Yacht');
});

