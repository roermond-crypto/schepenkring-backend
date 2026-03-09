<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('admin search ignores selected type filter and finds matching users across the user table', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
        'name' => 'Main Admin',
    ]);

    User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'name' => 'Client Search Match',
        'email' => 'client-search@example.test',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/admin/users?type=ADMIN&search=client');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Client Search Match')
        ->assertJsonPath('data.0.type', UserType::CLIENT->value);
});

test('employee search is scoped to users in their own locations', function () {
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
        'name' => 'Employee User',
    ]);

    $employee->locations()->attach($locationA->id, ['role' => 'LOCATION_MANAGER']);

    User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'name' => 'Client In Amsterdam',
        'client_location_id' => $locationA->id,
    ]);

    User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'name' => 'Client In Rotterdam',
        'client_location_id' => $locationB->id,
    ]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/admin/users?type=ADMIN&search=client');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Client In Amsterdam')
        ->assertJsonPath('data.0.client_location_id', $locationA->id);
});
