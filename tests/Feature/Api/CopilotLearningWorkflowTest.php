<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\AuditLog;
use App\Models\CopilotAction;
use App\Models\CopilotActionPhrase;
use App\Models\CopilotActionSuggestion;
use App\Models\CopilotAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('copilot mining creates a pending action suggestion from repeated audit history', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    foreach ([41, 42, 43] as $id) {
        AuditLog::create([
            'action' => 'users.updated',
            'risk_level' => 'LOW',
            'result' => 'SUCCESS',
            'actor_id' => $admin->id,
            'target_type' => User::class,
            'target_id' => $id,
            'entity_type' => User::class,
            'entity_id' => $id,
            'meta' => [
                'path' => "api/admin/users/{$id}",
                'method' => 'PATCH',
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = $this->postJson('/api/admin/copilot/suggestions/mine');

    $response->assertOk();

    $suggestion = CopilotActionSuggestion::query()
        ->where('route_template', '/admin/users/{user_id}')
        ->first();

    expect($suggestion)->not->toBeNull();
    expect($suggestion->suggestion_type)->toBe('action');
    expect($suggestion->status)->toBe('pending');
    expect($suggestion->action_id)->toBe('user.view');
    expect($suggestion->evidence_count)->toBe(3);
});

test('copilot mining creates a phrase suggestion from repeated misses and past successful actions', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $action = CopilotAction::create([
        'action_id' => 'user.view',
        'title' => 'Open user',
        'module' => 'users',
        'route_template' => '/admin/users/{user_id}',
        'required_params' => ['user_id'],
        'enabled' => true,
    ]);

    CopilotAuditEvent::create([
        'user_id' => $admin->id,
        'source' => 'header',
        'input_text' => 'open user profile',
        'selected_action_id' => 'user.view',
        'status' => 'resolved',
        'created_at' => now(),
    ]);

    foreach (range(1, 3) as $index) {
        CopilotAuditEvent::create([
            'user_id' => $admin->id,
            'source' => 'header',
            'input_text' => 'open client profile',
            'status' => 'no_match',
            'failure_reason' => 'no_action',
            'created_at' => now()->addSeconds($index),
        ]);
    }

    $response = $this->postJson('/api/admin/copilot/suggestions/mine');

    $response->assertOk();

    $suggestion = CopilotActionSuggestion::query()
        ->where('suggestion_type', 'phrase')
        ->where('target_copilot_action_id', $action->id)
        ->first();

    expect($suggestion)->not->toBeNull();
    expect($suggestion->status)->toBe('pending');
    expect(collect($suggestion->phrases)->pluck('phrase')->all())->toContain('open client profile');
});

test('admin can approve a generated copilot suggestion into a live action', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $suggestion = CopilotActionSuggestion::create([
        'suggestion_key' => 'test-open-user',
        'suggestion_type' => 'action',
        'action_id' => 'user.view',
        'title' => 'Open user',
        'short_description' => 'Open a user detail page.',
        'module' => 'users',
        'route_template' => '/admin/users/{user_id}',
        'required_params' => ['user_id'],
        'phrases' => [
            ['phrase' => 'open user', 'language' => 'en', 'priority' => 70],
            ['phrase' => 'show user', 'language' => 'en', 'priority' => 60],
        ],
        'confidence' => 0.84,
        'evidence_count' => 4,
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/admin/copilot/suggestions/{$suggestion->id}/approve");

    $response->assertOk()
        ->assertJsonPath('status', 'approved')
        ->assertJsonPath('action_id', 'user.view');

    $action = CopilotAction::query()->where('action_id', 'user.view')->first();

    expect($action)->not->toBeNull();
    expect(CopilotActionPhrase::query()->where('copilot_action_id', $action->id)->count())->toBe(2);
    expect($suggestion->fresh()->created_action_id)->toBe($action->id);
});
