<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin can create a harbor', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/admin/harbors', [
        'name' => 'Rotterdam Harbor',
        'code' => 'rtm',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Rotterdam Harbor')
        ->assertJsonPath('data.code', 'RTM')
        ->assertJsonPath('data.status', 'ACTIVE');

    $this->assertDatabaseHas('locations', [
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);
});

test('admin can update a harbor', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $harbor = Location::create([
        'name' => 'Old Harbor',
        'code' => 'OLD',
        'status' => 'ACTIVE',
    ]);

    $response = $this->patchJson("/api/admin/harbors/{$harbor->id}", [
        'name' => 'Updated Harbor',
        'status' => 'INACTIVE',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Harbor')
        ->assertJsonPath('data.code', 'OLD')
        ->assertJsonPath('data.status', 'INACTIVE');

    $this->assertDatabaseHas('locations', [
        'id' => $harbor->id,
        'name' => 'Updated Harbor',
        'status' => 'INACTIVE',
    ]);
});

test('admin cannot delete a harbor that is still in use', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $harbor = Location::create([
        'name' => 'Busy Harbor',
        'code' => 'BUSY',
        'status' => 'ACTIVE',
    ]);

    User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $harbor->id,
    ]);

    $response = $this->deleteJson("/api/admin/harbors/{$harbor->id}");

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Harbor is still in use and cannot be deleted.')
        ->assertJsonPath('usage.clients', 1);

    $this->assertDatabaseHas('locations', [
        'id' => $harbor->id,
    ]);
});

test('admin can delete an unused harbor', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $harbor = Location::create([
        'name' => 'Empty Harbor',
        'code' => 'EMP',
        'status' => 'ACTIVE',
    ]);

    $response = $this->deleteJson("/api/admin/harbors/{$harbor->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'deleted');

    $this->assertDatabaseMissing('locations', [
        'id' => $harbor->id,
    ]);
});
