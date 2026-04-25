<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\Task;
use App\Models\TaskAutomation;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Models\Yacht;
use Laravel\Sanctum\Sanctum;

test('tasks api renders boat name and id for legacy boat placeholder titles', function () {
    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
    ]);

    $yacht = Yacht::create([
        'user_id' => $client->id,
        'location_id' => $location->id,
        'vessel_id' => 'VESSEL-99',
        'boat_name' => 'Blue Pearl',
        'boat_type' => 'Motor Yacht',
        'status' => 'Draft',
    ]);

    $task = Task::create([
        'title' => 'Follow up with client about boat #{boat_id}',
        'description' => 'Placeholder title test',
        'priority' => 'High',
        'status' => 'New',
        'assignment_status' => 'accepted',
        'assigned_to' => $client->id,
        'user_id' => $client->id,
        'created_by' => $client->id,
        'type' => 'assigned',
        'location_id' => $location->id,
        'client_visible' => true,
        // Legacy case: task title still has template placeholder and no yacht_id stored.
        'yacht_id' => null,
    ]);

    $template = TaskAutomationTemplate::create([
        'name' => 'Legacy sync template',
        'trigger_event' => 'boat_created',
        'schedule_type' => 'relative',
        'delay_value' => 1,
        'delay_unit' => 'days',
        'title' => 'Legacy template',
        'description' => 'Legacy',
        'priority' => 'Medium',
        'default_assignee_type' => 'seller',
        'is_active' => true,
        'location_id' => $location->id,
    ]);

    TaskAutomation::create([
        'template_id' => $template->id,
        'trigger_event' => 'boat_created',
        'related_type' => Yacht::class,
        'related_id' => $yacht->id,
        'assigned_user_id' => $client->id,
        'due_at' => now()->addDay(),
        'status' => 'synced',
        'created_task_id' => $task->id,
        'location_id' => $location->id,
    ]);

    Sanctum::actingAs($client);

    $response = $this->getJson('/api/tasks');
    $response->assertOk();
    $response->assertJsonPath('0.title', "Follow up with client about boat Blue Pearl (#{$yacht->id})");
});

