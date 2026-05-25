<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\CopilotAction;
use App\Models\CopilotActionPhrase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('admin copilot resolves create boat as a deterministic action', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $this->postJson('/api/copilot/resolve', [
        'text' => 'create boat',
        'source' => 'header',
        'context' => ['language' => 'en'],
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.action_id', 'create.boat')
        ->assertJsonPath('actions.0.deeplink', '/admin/yachts/new')
        ->assertJsonPath('actions.0.params', [])
        ->assertJsonPath('confidence', 1);
});

test('employee cannot use admin create boat copilot action', function () {
    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($employee);

    $this->postJson('/api/copilot/resolve', [
        'text' => 'create boat',
        'source' => 'header',
        'context' => ['language' => 'en'],
    ])
        ->assertOk()
        ->assertJsonPath('actions', []);
});

test('admin workflow can draft validate and execute create boat action', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $draft = $this->postJson('/api/admin/copilot/draft', [
        'prompt' => 'open the new yacht form',
        'language' => 'en',
    ])
        ->assertOk()
        ->assertJsonPath('selected_action.action_id', 'create.boat')
        ->json();

    expect($draft['selected_action']['params'])->toBe([]);

    $token = $this->postJson('/api/admin/copilot/validate', [
        'action_id' => 'create.boat',
        'payload' => [],
    ])
        ->assertOk()
        ->assertJsonPath('action_id', 'create.boat')
        ->json('validation_token');

    $this->postJson('/api/admin/copilot/execute', [
        'validation_token' => $token,
    ])
        ->assertOk()
        ->assertJsonPath('execution.execution_type', 'deeplink')
        ->assertJsonPath('execution.deeplink', '/admin/yachts/new')
        ->assertJsonPath('execution.unfilled_params', []);
});

test('create boat action and phrases are migration seeded', function () {
    $action = CopilotAction::query()->where('action_id', 'create.boat')->first();

    expect($action)->not->toBeNull()
        ->and($action->route_template)->toBe('/admin/yachts/new')
        ->and($action->required_role)->toBe('admin');

    expect(CopilotActionPhrase::query()
        ->where('copilot_action_id', $action->id)
        ->where('phrase', 'create boat')
        ->exists())->toBeTrue();
});
