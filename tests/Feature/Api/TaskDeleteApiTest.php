<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('employee assignee can delete an assigned task', function () {
    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $assignee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $creator = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $assignee->locations()->attach($location->id, ['role' => 'LOCATION_EMPLOYEE']);
    $creator->locations()->attach($location->id, ['role' => 'LOCATION_MANAGER']);

    $task = Task::create([
        'title' => 'Inspect hull',
        'priority' => 'High',
        'status' => 'New',
        'assignment_status' => 'pending',
        'assigned_to' => $assignee->id,
        'user_id' => null,
        'created_by' => $creator->id,
        'type' => 'assigned',
        'location_id' => $location->id,
    ]);

    Sanctum::actingAs($assignee);

    $response = $this->deleteJson("/api/tasks/{$task->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'Task deleted successfully');

    $this->assertDatabaseMissing('tasks', [
        'id' => $task->id,
    ]);
});
