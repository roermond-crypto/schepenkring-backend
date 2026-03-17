<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\AiDailyInsight;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin can list latest and detailed daily insights', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $insight = AiDailyInsight::create([
        'period_start' => Carbon::parse('2026-03-13T02:00:00Z'),
        'period_end' => Carbon::parse('2026-03-14T02:00:00Z'),
        'product' => 'Schepen-Kring',
        'environment' => 'testing',
        'timezone' => 'Europe/Amsterdam',
        'status' => 'completed',
        'overall_status' => 'warning',
        'headline' => 'Employees without location_id caused chat failures.',
        'model' => 'gpt-5',
        'summary_json' => [
            'overall_status' => 'warning',
            'headline' => 'Employees without location_id caused chat failures.',
        ],
        'top_findings_json' => [
            [
                'severity' => 'high',
                'type' => 'bug',
                'title' => 'Employee location issue',
                'evidence' => ['23 null-location failures'],
                'likely_root_cause' => 'Missing employee location assignment',
                'recommended_action' => 'Assign a location to employee users',
            ],
        ],
        'performance_issues_json' => [],
        'security_signals_json' => [],
        'priority_actions_json' => ['Fix employee location mapping'],
        'raw_input_json' => ['task' => 'Analyze'],
        'raw_output_json' => ['summary' => ['overall_status' => 'warning']],
        'usage_json' => ['total_tokens' => 660],
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/insights/latest')
        ->assertOk()
        ->assertJsonPath('data.id', $insight->id)
        ->assertJsonPath('data.headline', 'Employees without location_id caused chat failures.');

    $this->getJson('/api/admin/insights?per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.id', $insight->id)
        ->assertJsonPath('data.0.overall_status', 'warning');

    $this->getJson("/api/admin/insights/{$insight->id}?include_raw=1")
        ->assertOk()
        ->assertJsonPath('data.raw_input_json.task', 'Analyze')
        ->assertJsonPath('data.raw_output_json.summary.overall_status', 'warning');
});

test('admin can trigger manual AI insight generation from the api', function () {
    config()->set('services.openai.key', 'test-openai-key');
    config()->set('services.openai.insights_model', 'gpt-5');

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    AuditLog::create([
        'action' => 'auth.login',
        'risk_level' => 'LOW',
        'result' => 'SUCCESS',
        'actor_id' => $admin->id,
        'location_id' => null,
        'meta' => [
            'path' => '/api/auth/login',
            'method' => 'POST',
        ],
        'ip_address' => '102.93.11.5',
        'ip_hash' => hash('sha256', '102.93.11.5'),
        'created_at' => Carbon::parse('2026-03-14T00:30:00Z'),
        'updated_at' => Carbon::parse('2026-03-14T00:30:00Z'),
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_api_456',
            'model' => 'gpt-5',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'summary' => [
                            'overall_status' => 'info',
                            'headline' => 'No major incidents were detected in this window.',
                        ],
                        'top_findings' => [],
                        'performance_issues' => [],
                        'security_signals' => [],
                        'priority_actions' => [
                            'Continue monitoring audit and Sentry trends',
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 200,
                'output_tokens' => 40,
                'total_tokens' => 240,
            ],
        ]),
    ]);

    Sanctum::actingAs($admin);

    $this->postJson('/api/admin/insights/generate', [
        'start' => '2026-03-13T02:00:00Z',
        'end' => '2026-03-14T02:00:00Z',
        'timezone' => 'UTC',
    ])
        ->assertOk()
        ->assertJsonPath('data.overall_status', 'info')
        ->assertJsonPath('data.headline', 'No major incidents were detected in this window.');

    expect(AiDailyInsight::query()->count())->toBe(1);
});
