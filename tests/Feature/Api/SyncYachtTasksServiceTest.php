<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\Task;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Models\Yacht;
use App\Services\SyncYachtTasksService;

test('sync yacht tasks renders placeholders, attaches the yacht, and respects boat type filters', function () {
    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
        'name' => 'Iris Client',
    ]);

    TaskAutomationTemplate::create([
        'name' => 'Matching client follow-up',
        'trigger_event' => 'boat_created',
        'schedule_type' => 'relative',
        'delay_value' => 10,
        'delay_unit' => 'days',
        'title' => 'Follow up with {client_name} about boat #{boat_id}',
        'description' => 'Check {boat_name} ({boat_type}) via {boat_url} and confirm {vessel_id}.',
        'priority' => 'High',
        'default_assignee_type' => 'seller',
        'is_active' => true,
        'boat_type_filter' => ['motor yacht'],
        'location_id' => $location->id,
    ]);

    TaskAutomationTemplate::create([
        'name' => 'Non-matching sail follow-up',
        'trigger_event' => 'boat_created',
        'schedule_type' => 'relative',
        'delay_value' => 5,
        'delay_unit' => 'days',
        'title' => 'This should not be created for #{boat_id}',
        'description' => 'Filtered out by boat type.',
        'priority' => 'High',
        'default_assignee_type' => 'seller',
        'is_active' => true,
        'boat_type_filter' => ['sailing yacht'],
        'location_id' => $location->id,
    ]);

    $yacht = Yacht::create([
        'user_id' => $client->id,
        'location_id' => $location->id,
        'vessel_id' => 'VESSEL-42',
        'boat_name' => 'Sea Breeze',
        'boat_type' => 'Motor Yacht',
        'status' => 'Draft',
    ]);

    app(SyncYachtTasksService::class)->syncForYacht($yacht);

    $tasks = Task::query()->orderBy('id')->get();

    expect($tasks)->toHaveCount(1);

    $task = $tasks->first();

    expect($task->title)->toBe("Follow up with Iris Client about boat Sea Breeze (#{$yacht->id})")
        ->and($task->title)->not->toContain('#{boat_id}')
        ->and($task->description)->toContain('Sea Breeze')
        ->and($task->description)->toContain('Motor Yacht')
        ->and($task->description)->toContain('VESSEL-42')
        ->and($task->description)->not->toContain('{boat_name}')
        ->and($task->yacht_id)->toBe($yacht->id)
        ->and($task->user_id)->toBe($client->id)
        ->and($task->client_visible)->toBeTrue();
});
