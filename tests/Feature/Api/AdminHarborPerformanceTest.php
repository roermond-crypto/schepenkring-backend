<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Boat;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('admin can fetch harbor performance metrics', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $harbor = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $harbor->id,
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $harbor->users()->attach($employee->id, ['role' => 'LOCATION_MANAGER']);

    Boat::create([
        'location_id' => $harbor->id,
        'client_id' => null,
        'name' => 'Demo Boat',
        'status' => 'DRAFT',
    ]);

    Lead::create([
        'location_id' => $harbor->id,
        'status' => 'new',
        'source_url' => 'https://example.test/widget',
        'name' => 'Lead One',
        'email' => 'lead@example.test',
    ]);

    Conversation::create([
        'location_id' => $harbor->id,
        'channel' => 'web_widget',
        'status' => 'open',
    ]);

    Task::create([
        'title' => 'Follow up',
        'status' => 'New',
        'location_id' => $harbor->id,
    ]);

    $response = $this->getJson('/api/admin/harbors/performance?range=30d');

    $response->assertOk()
        ->assertJsonPath('range', '30d')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'AMS')
        ->assertJsonPath('data.0.metrics.clients_total', 1)
        ->assertJsonPath('data.0.metrics.staff_total', 1)
        ->assertJsonPath('data.0.metrics.boats_total', 1)
        ->assertJsonPath('data.0.metrics.leads_created', 1)
        ->assertJsonPath('data.0.metrics.open_conversations', 1)
        ->assertJsonPath('data.0.metrics.open_tasks', 1);
});
