<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Jobs\AnalyzeIssueWithAI;
use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

test('user can report an issue with screenshot and metadata without waiting for ai', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/issues', [
        'description' => 'The report modal takes too long to close.',
        'page_url' => 'https://www.nauticsecure.nl/nl/dashboard/issues',
        'browser' => 'Chrome',
        'device' => 'desktop',
        'logs' => [['level' => 'error', 'message' => 'Timeout']],
        'screenshot' => UploadedFile::fake()->image('issue.png'),
    ]);

    $response
        ->assertAccepted()
        ->assertJsonPath('message', 'Issue reported successfully. We will review it.')
        ->assertJsonPath('ai_status', 'pending')
        ->assertJsonPath('screenshot_status', 'stored');

    $issue = Issue::query()->firstOrFail();

    expect($issue->description)->toBe('The report modal takes too long to close.')
        ->and($issue->page_url)->toBe('https://www.nauticsecure.nl/nl/dashboard/issues')
        ->and($issue->browser)->toBe('Chrome')
        ->and($issue->device)->toBe('desktop')
        ->and($issue->logs)->toBe([['level' => 'error', 'message' => 'Timeout']])
        ->and($issue->screenshot_status)->toBe('stored')
        ->and($issue->ai_status)->toBe('pending');

    Storage::disk('local')->assertExists($issue->screenshot_path);
    Queue::assertPushed(AnalyzeIssueWithAI::class);

    expect(AuditLog::query()->where('action', 'issue.created')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'issue.screenshot_uploaded')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'issue.ai_queued')->exists())->toBeTrue();
});

test('admin can filter issue reports and receives signed screenshot preview url', function () {
    Storage::fake('local');

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);
    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
    ]);

    Storage::disk('local')->put('issues/1/screenshot.png', 'fake');

    $issue = Issue::create([
        'user_id' => $client->id,
        'title' => 'Slow modal',
        'description' => 'The modal blocks while AI runs.',
        'status' => 'new',
        'page_url' => 'https://example.test/dashboard',
        'screenshot_path' => 'issues/1/screenshot.png',
        'screenshot_status' => 'stored',
        'ai_status' => 'failed',
        'ai_priority' => 'high',
    ]);

    Issue::create([
        'user_id' => $client->id,
        'title' => 'Other issue',
        'description' => 'Different problem.',
        'status' => 'closed',
        'ai_status' => 'completed',
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/issues?ai_status=failed&ai_priority=high&has_screenshot=1&page_url=dashboard')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $issue->id)
        ->assertJsonPath('data.0.screenshot_status', 'stored')
        ->assertJsonPath('data.0.user.role', 'client')
        ->assertJson(fn ($json) => $json->whereType('data.0.screenshot_url', 'string')->etc());
});

test('admin can update issue status and retry ai resets pending state', function () {
    Queue::fake();

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);
    $client = User::factory()->create();

    $issue = Issue::create([
        'user_id' => $client->id,
        'title' => 'AI failed',
        'description' => 'The AI job failed.',
        'status' => 'failed',
        'ai_status' => 'failed',
        'ai_error' => 'OpenAI timeout',
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/issues/{$issue->id}", [
        'status' => 'in_review',
        'ai_priority' => 'high',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_review')
        ->assertJsonPath('data.ai_priority', 'high');

    $this->postJson("/api/admin/issues/{$issue->id}/retry-ai")
        ->assertOk()
        ->assertJsonPath('message', 'AI analysis re-queued.');

    $issue->refresh();

    expect($issue->status)->toBe('ai_pending')
        ->and($issue->ai_status)->toBe('pending')
        ->and($issue->ai_error)->toBeNull();

    Queue::assertPushed(AnalyzeIssueWithAI::class);
    expect(AuditLog::query()->where('action', 'issue.status_changed')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'issue.ai_retried')->exists())->toBeTrue();
});
