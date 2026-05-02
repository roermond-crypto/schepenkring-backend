<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\Task;
use App\Models\TaskAutomationExecutionLog;
use App\Models\TaskAutomationRule;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use App\Models\Yacht;
use App\Services\TaskAutomationRuleEngine;
use Laravel\Sanctum\Sanctum;

test('admin can create a task automation rule and simulate matching yacht tasks', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $seller = User::factory()->create([
        'type' => UserType::SELLER,
        'status' => UserStatus::ACTIVE,
        'name' => 'Seller Person',
    ]);

    $location = Location::create([
        'name' => 'Amsterdam Harbor',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $template = TaskAutomationTemplate::create([
        'name' => 'Activate yacht follow-up',
        'trigger_event' => 'boat_status_activated',
        'schedule_type' => 'relative',
        'delay_value' => 1,
        'delay_unit' => 'days',
        'title' => 'Review {boat_name}',
        'description' => 'Call {client_name} about {boat_type}.',
        'priority' => 'High',
        'default_assignee_type' => 'seller',
        'is_active' => true,
        'location_id' => $location->id,
    ]);

    $yacht = Yacht::create([
        'user_id' => $seller->id,
        'ref_harbor_id' => $location->id,
        'boat_name' => 'Blue Horizon',
        'boat_type' => 'Motorboat',
        'year' => 2018,
        'location_city' => 'Amsterdam',
        'status' => 'Active',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/task-automation-rules', [
        'name' => 'Activated motorboat seller task',
        'trigger_event' => 'boat_status_activated',
        'is_active' => true,
        'target_role' => 'client',
        'target_user_type' => 'seller',
        'assignee_rule' => 'seller',
        'boat_types' => ['Motorboat'],
        'boat_year_from' => 2010,
        'boat_year_to' => 2025,
        'location_filter' => 'Amsterdam',
        'visibility_delay_hours' => 2,
        'template_ids' => [$template->id],
        'location_id' => $location->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Activated motorboat seller task')
        ->assertJsonPath('templates.0.id', $template->id);

    $simulate = $this->postJson('/api/task-automation-rules/simulate', [
        'trigger_event' => 'boat_status_activated',
        'entity_type' => Yacht::class,
        'entity_id' => $yacht->id,
    ]);

    $simulate->assertOk()
        ->assertJsonPath('matched_rules', 1)
        ->assertJsonPath('preview.0.tasks.0.title', 'Review Blue Horizon')
        ->assertJsonPath('preview.0.tasks.0.assignee_id', $seller->id);
});

test('rule engine creates tasks once and writes execution logs', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $seller = User::factory()->create([
        'type' => UserType::SELLER,
        'status' => UserStatus::ACTIVE,
    ]);

    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $template = TaskAutomationTemplate::create([
        'name' => 'Created yacht task',
        'trigger_event' => 'boat_created',
        'schedule_type' => 'relative',
        'delay_value' => 1,
        'delay_unit' => 'days',
        'title' => 'Check {boat_name}',
        'description' => 'Automated follow-up',
        'priority' => 'Medium',
        'default_assignee_type' => 'seller',
        'is_active' => true,
        'location_id' => $location->id,
    ]);

    TaskAutomationRule::create([
        'name' => 'Created yacht client task',
        'trigger_event' => 'boat_created',
        'is_active' => true,
        'target_role' => 'client',
        'target_user_type' => 'seller',
        'assignee_rule' => 'seller',
        'location_id' => $location->id,
    ])->templates()->sync([$template->id => ['sort_order' => 0]]);

    $yacht = Yacht::create([
        'user_id' => $seller->id,
        'ref_harbor_id' => $location->id,
        'boat_name' => 'Sea Sprint',
        'boat_type' => 'Sailship',
        'status' => 'Draft',
    ]);

    app(TaskAutomationRuleEngine::class)->handle('boat_created', $yacht, $admin);
    app(TaskAutomationRuleEngine::class)->handle('boat_created', $yacht, $admin);

    expect(Task::query()->where('title', 'Check Sea Sprint')->count())->toBe(1)
        ->and(TaskAutomationExecutionLog::query()->where('result', 'success')->count())->toBe(2);
});

test('admin can retry a failed automation execution log', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $seller = User::factory()->create([
        'type' => UserType::SELLER,
        'status' => UserStatus::ACTIVE,
    ]);

    $template = TaskAutomationTemplate::create([
        'name' => 'Retry task template',
        'trigger_event' => 'boat_created',
        'schedule_type' => 'relative',
        'delay_value' => 1,
        'delay_unit' => 'days',
        'title' => 'Retry {boat_name}',
        'description' => 'Retry description',
        'priority' => 'Medium',
        'default_assignee_type' => 'seller',
        'is_active' => true,
    ]);

    TaskAutomationRule::create([
        'name' => 'Retryable yacht task',
        'trigger_event' => 'boat_created',
        'is_active' => true,
        'target_role' => 'client',
        'target_user_type' => 'seller',
        'assignee_rule' => 'seller',
    ])->templates()->sync([$template->id => ['sort_order' => 0]]);

    $yacht = Yacht::create([
        'user_id' => $seller->id,
        'boat_name' => 'Retry Boat',
        'boat_type' => 'Motorboat',
        'status' => 'Draft',
    ]);

    $log = TaskAutomationExecutionLog::create([
        'trigger_event' => 'boat_created',
        'entity_type' => Yacht::class,
        'entity_id' => $yacht->id,
        'result' => 'failed',
        'reason' => 'temporary_error',
        'created_at' => now(),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->postJson("/api/task-automation-rules/logs/{$log->id}/retry");

    $response->assertOk()
        ->assertJsonPath('message', 'Automation retry completed.');

    expect(Task::query()->where('title', 'Retry Retry Boat')->count())->toBe(1);
});
