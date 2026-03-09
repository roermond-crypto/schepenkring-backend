<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('resource audit endpoint resolves client slug to user model', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson("/api/audit-logs/client/{$client->id}");

    $response->assertOk()
        ->assertJsonPath('data.0.entity_type', User::class)
        ->assertJsonPath('data.0.entity_id', $client->id);
});

test('admin audit endpoint resolves user entity type filters', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson("/api/admin/audit?entity_type=user&entity_id={$client->id}");

    $response->assertOk()
        ->assertJsonPath('data.0.entity_type', User::class)
        ->assertJsonPath('data.0.entity_id', $client->id);
});
