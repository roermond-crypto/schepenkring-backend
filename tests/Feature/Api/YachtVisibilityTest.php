<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('guest cannot list yachts', function () {
    $this->getJson('/api/yachts')
        ->assertUnauthorized();
});

test('client only sees their own yachts', function () {
    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
    ]);

    $otherClient = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
    ]);

    $ownedYacht = Yacht::create([
        'user_id' => $client->id,
        'ref_harbor_id' => $location->id,
        'vessel_id' => 'SK-CLIENT-001',
        'boat_name' => 'Owned Yacht',
    ]);

    Yacht::create([
        'user_id' => $otherClient->id,
        'ref_harbor_id' => $location->id,
        'vessel_id' => 'SK-CLIENT-002',
        'boat_name' => 'Other Yacht',
    ]);

    Sanctum::actingAs($client);

    $this->getJson('/api/yachts')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $ownedYacht->id)
        ->assertJsonPath('0.boat_name', 'Owned Yacht');
});

test('employee only sees yachts from assigned harbors', function () {
    $locationA = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $locationB = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($locationA->id, ['role' => 'LOCATION_MANAGER']);

    $clientA = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $locationA->id,
    ]);

    $clientB = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $locationB->id,
    ]);

    $harborScopedYacht = Yacht::create([
        'user_id' => $clientA->id,
        'ref_harbor_id' => $locationA->id,
        'vessel_id' => 'SK-EMPLOYEE-001',
        'boat_name' => 'Harbor Scoped Yacht',
    ]);

    $ownerFallbackYacht = Yacht::create([
        'user_id' => $clientA->id,
        'ref_harbor_id' => null,
        'vessel_id' => 'SK-EMPLOYEE-002',
        'boat_name' => 'Owner Fallback Yacht',
    ]);

    Yacht::create([
        'user_id' => $clientB->id,
        'ref_harbor_id' => $locationB->id,
        'vessel_id' => 'SK-EMPLOYEE-003',
        'boat_name' => 'Foreign Yacht',
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/yachts')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', $harborScopedYacht->id)
        ->assertJsonPath('1.id', $ownerFallbackYacht->id);
});

test('client cannot view another users yacht', function () {
    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
    ]);

    $otherClient = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
    ]);

    $otherYacht = Yacht::create([
        'user_id' => $otherClient->id,
        'ref_harbor_id' => $location->id,
        'vessel_id' => 'SK-CLIENT-003',
        'boat_name' => 'Private Yacht',
    ]);

    Sanctum::actingAs($client);

    $this->getJson("/api/yachts/{$otherYacht->id}")
        ->assertNotFound();
});
