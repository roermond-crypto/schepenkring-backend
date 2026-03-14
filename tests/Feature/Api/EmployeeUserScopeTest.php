<?php

use App\Enums\LocationRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('employee cannot access admin users route', function () {
    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $location->users()->attach($employee->id, [
        'role' => LocationRole::LOCATION_EMPLOYEE->value,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/admin/users')
        ->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('employee users route returns only client users from the employees location', function () {
    $employeeLocation = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $otherLocation = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
        'name' => 'Jane Employee',
        'email' => 'jane.employee@example.com',
    ]);

    $employeeLocation->users()->attach($employee->id, [
        'role' => LocationRole::LOCATION_EMPLOYEE->value,
    ]);

    $sameLocationClient = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'name' => 'Peter Client',
        'email' => 'peter.client@example.com',
        'client_location_id' => $employeeLocation->id,
    ]);

    User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'name' => 'Other Location Client',
        'email' => 'other.client@example.com',
        'client_location_id' => $otherLocation->id,
    ]);

    User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);

    User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
        'name' => 'Other Employee',
        'email' => 'other.employee@example.com',
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/employee/users?per_page=25')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $sameLocationClient->id)
        ->assertJsonPath('data.0.role', 'client')
        ->assertJsonPath('data.0.client_location_id', $employeeLocation->id);
});

test('employee can view only own-location client details from employee users route', function () {
    $employeeLocation = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $otherLocation = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $employeeLocation->users()->attach($employee->id, [
        'role' => LocationRole::LOCATION_EMPLOYEE->value,
    ]);

    $sameLocationClient = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $employeeLocation->id,
    ]);

    $otherLocationClient = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $otherLocation->id,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson("/api/employee/users/{$sameLocationClient->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $sameLocationClient->id);

    $this->getJson("/api/employee/users/{$otherLocationClient->id}")
        ->assertNotFound();
});
