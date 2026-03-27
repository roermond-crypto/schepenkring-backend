<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\PlatformError;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin can filter platform errors with legacy frontend query params', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $matchingError = PlatformError::create([
        'title' => 'Checkout crash on reservation submit',
        'message' => 'The booking payment form crashes during checkout.',
        'level' => 'error',
        'project' => 'frontend-web',
        'source' => 'frontend',
        'environment' => 'production',
        'status' => 'unresolved',
        'occurrences_count' => 9,
        'users_affected' => 3,
        'first_seen_at' => now()->subHour(),
        'last_seen_at' => now(),
    ]);

    PlatformError::create([
        'title' => 'Webhook timeout',
        'message' => 'The backend webhook timed out while processing a lead.',
        'level' => 'error',
        'project' => 'backend-api',
        'source' => 'backend',
        'environment' => 'production',
        'status' => 'unresolved',
        'occurrences_count' => 4,
        'users_affected' => 1,
        'first_seen_at' => now()->subHours(2),
        'last_seen_at' => now()->subMinutes(30),
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/errors?project=frontend-web&search=checkout')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingError->id)
        ->assertJsonPath('data.0.project', 'frontend-web');
});

test('admin can filter platform errors by source or matching project alias', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $frontendError = PlatformError::create([
        'title' => 'Frontend rendering failure',
        'message' => 'A client-side exception occurred while loading the widget.',
        'level' => 'error',
        'project' => 'frontend-web',
        'source' => 'frontend',
        'environment' => 'production',
        'status' => 'unresolved',
        'occurrences_count' => 6,
        'users_affected' => 2,
        'first_seen_at' => now()->subHour(),
        'last_seen_at' => now(),
    ]);

    PlatformError::create([
        'title' => 'Backend queue failure',
        'message' => 'A worker failed to process the queued notification job.',
        'level' => 'error',
        'project' => 'backend-api',
        'source' => 'backend',
        'environment' => 'production',
        'status' => 'unresolved',
        'occurrences_count' => 2,
        'users_affected' => 1,
        'first_seen_at' => now()->subHours(3),
        'last_seen_at' => now()->subMinutes(10),
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/errors?source=frontend-web')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $frontendError->id);
});
