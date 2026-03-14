<?php

use App\Enums\LocationRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Conversation;
use App\Models\Location;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin can update an employee location from the account endpoint', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson(
        "/api/admin/users/{$employee->id}",
        [
            'location_id' => $location->id,
            'location_role' => LocationRole::LOCATION_EMPLOYEE->value,
        ],
        ['Idempotency-Key' => 'employee-location-account-update-1']
    )
        ->assertOk()
        ->assertJsonPath('data.location_id', $location->id)
        ->assertJsonPath('data.location.id', $location->id)
        ->assertJsonPath('data.location_role', LocationRole::LOCATION_EMPLOYEE->value)
        ->assertJsonPath('data.role', 'employee')
        ->assertJsonCount(1, 'data.locations');

    $this->assertDatabaseHas('location_user', [
        'user_id' => $employee->id,
        'location_id' => $location->id,
        'role' => LocationRole::LOCATION_EMPLOYEE->value,
    ]);
});

test('admin locations endpoint shows assigned employees for each location', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
        'name' => 'Dock Employee',
        'email' => 'dock.employee@example.test',
    ]);

    $employee->locations()->attach($location->id, [
        'role' => LocationRole::LOCATION_MANAGER->value,
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/locations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $location->id)
        ->assertJsonPath('data.0.employee_count', 1)
        ->assertJsonPath('data.0.employees.0.id', $employee->id)
        ->assertJsonPath('data.0.employees.0.name', 'Dock Employee')
        ->assertJsonPath('data.0.employees.0.location_role', LocationRole::LOCATION_MANAGER->value);
});

test('employee chat inbox is scoped to their location and includes ai replies', function () {
    $locationA = Location::create([
        'name' => 'Harbor A',
        'code' => 'HBA',
        'status' => 'ACTIVE',
    ]);

    $locationB = Location::create([
        'name' => 'Harbor B',
        'code' => 'HBB',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $employee->locations()->attach($locationA->id, [
        'role' => LocationRole::LOCATION_EMPLOYEE->value,
    ]);

    $visibleConversation = Conversation::create([
        'location_id' => $locationA->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    Message::create([
        'conversation_id' => $visibleConversation->id,
        'sender_type' => 'visitor',
        'text' => 'Client question for harbor A',
        'body' => 'Client question for harbor A',
        'channel' => 'web',
        'message_type' => 'text',
        'delivery_state' => 'sent',
    ]);

    Message::create([
        'conversation_id' => $visibleConversation->id,
        'sender_type' => 'ai',
        'text' => 'Automated answer for harbor A',
        'body' => 'Automated answer for harbor A',
        'channel' => 'web',
        'message_type' => 'text',
        'delivery_state' => 'sent',
    ]);

    $hiddenConversation = Conversation::create([
        'location_id' => $locationB->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    Message::create([
        'conversation_id' => $hiddenConversation->id,
        'sender_type' => 'ai',
        'text' => 'Automated answer for harbor B',
        'body' => 'Automated answer for harbor B',
        'channel' => 'web',
        'message_type' => 'text',
        'delivery_state' => 'sent',
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/chat/conversations?limit=20')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visibleConversation->id)
        ->assertJsonPath('data.0.location.id', $locationA->id);

    $this->getJson("/api/chat/conversations/{$visibleConversation->id}")
        ->assertOk()
        ->assertJsonPath('location.id', $locationA->id)
        ->assertJsonFragment([
            'sender_type' => 'ai',
            'text' => 'Automated answer for harbor A',
        ])
        ->assertJsonFragment([
            'sender_type' => 'visitor',
            'text' => 'Client question for harbor A',
        ]);

    $this->getJson("/api/chat/conversations/{$hiddenConversation->id}")
        ->assertForbidden();
});

test('employee board access requires an assigned location', function () {
    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/boards')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['location_id']);
});

test('employee can access the board for their assigned location', function () {
    $location = Location::create([
        'name' => 'Board Harbor',
        'code' => 'BRD',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $employee->locations()->attach($location->id, [
        'role' => LocationRole::LOCATION_EMPLOYEE->value,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson('/api/boards')
        ->assertOk()
        ->assertJsonPath('location_id', $location->id)
        ->assertJsonCount(3, 'columns');
});
