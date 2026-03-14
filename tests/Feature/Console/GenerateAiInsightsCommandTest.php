<?php

namespace Tests\Feature\Console;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\AiDailyInsight;
use App\Models\AuditLog;
use App\Models\PlatformError;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateAiInsightsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_and_stores_daily_ai_insights(): void
    {
        config()->set('services.openai.key', 'test-openai-key');
        config()->set('services.openai.insights_model', 'gpt-5');

        $start = Carbon::parse('2026-03-13T02:00:00Z');
        $end = Carbon::parse('2026-03-14T02:00:00Z');

        $employee = User::factory()->create([
            'type' => UserType::EMPLOYEE,
            'status' => UserStatus::ACTIVE,
        ]);

        AuditLog::create([
            'action' => 'open.chat',
            'risk_level' => 'LOW',
            'result' => 'SUCCESS',
            'actor_id' => $employee->id,
            'location_id' => null,
            'meta' => [
                'path' => '/nl/dashboard/employee/tasks',
                'method' => 'GET',
            ],
            'ip_address' => '195.85.176.250',
            'ip_hash' => hash('sha256', '195.85.176.250'),
            'created_at' => $end->copy()->subHour(),
            'updated_at' => $end->copy()->subHour(),
        ]);

        PlatformError::create([
            'reference_code' => 'ERR-INS001',
            'sentry_issue_id' => 'employee-chat-location-null',
            'title' => "Trying to get property 'location_id' of null",
            'message' => "Trying to get property 'location_id' of null",
            'level' => 'error',
            'environment' => 'testing',
            'route' => 'https://example.test/nl/dashboard/employee/tasks',
            'occurrences_count' => 23,
            'first_seen_at' => $start->copy()->addHours(6),
            'last_seen_at' => $end->copy()->subMinutes(15),
            'status' => 'unresolved',
            'tags' => [
                'role' => 'employee',
                'user_id' => (string) $employee->id,
                'location_id' => '',
            ],
            'last_event_sample_json' => [
                'tags' => [
                    'role' => 'employee',
                    'user_id' => (string) $employee->id,
                ],
                'contexts' => [
                    'trace' => [
                        'data' => [
                            'transaction.duration_ms' => 2800,
                        ],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_daily_123',
                'model' => 'gpt-5',
                'output' => [[
                    'type' => 'message',
                    'role' => 'assistant',
                    'status' => 'completed',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => [
                                'overall_status' => 'warning',
                                'headline' => 'Employee dashboard errors correlate with missing location assignments.',
                            ],
                            'top_findings' => [[
                                'severity' => 'high',
                                'type' => 'bug',
                                'title' => 'Employees without location_id trigger chat failures',
                                'evidence' => [
                                    '23 occurrences of null location errors on the employee tasks page',
                                    'Audit activity shows employee chat access without a location assignment',
                                ],
                                'likely_root_cause' => 'Employee records or access paths allow null location resolution.',
                                'recommended_action' => 'Require employee location assignments and guard null location access in employee queries.',
                            ]],
                            'performance_issues' => [[
                                'severity' => 'medium',
                                'route' => '/nl/dashboard/employee/tasks',
                                'problem' => 'High latency',
                                'evidence' => [
                                    'avg_ms' => 2800,
                                    'p95_ms' => 2800,
                                    'count' => 1,
                                ],
                                'recommended_action' => 'Review location-scoped eager loading for employee task and chat data.',
                            ]],
                            'security_signals' => [[
                                'severity' => 'low',
                                'title' => 'Same IP used across multiple accounts',
                                'recommended_action' => 'Confirm whether the shared IP is expected office traffic.',
                            ]],
                            'priority_actions' => [
                                'Fix employee location assignment enforcement',
                                'Add null guards around employee location access',
                                'Review employee dashboard query performance',
                            ],
                        ], JSON_UNESCAPED_SLASHES),
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => 500,
                    'output_tokens' => 160,
                    'total_tokens' => 660,
                ],
            ]),
        ]);

        $this->artisan('app:generate-ai-insights', [
            '--start' => $start->toIso8601String(),
            '--end' => $end->toIso8601String(),
            '--timezone' => 'UTC',
        ])->assertSuccessful();

        $insight = AiDailyInsight::query()->first();

        $this->assertNotNull($insight);
        $this->assertSame('completed', $insight->status);
        $this->assertSame('warning', $insight->overall_status);
        $this->assertSame('Employee dashboard errors correlate with missing location assignments.', $insight->headline);
        $this->assertSame('resp_daily_123', $insight->openai_response_id);

        Http::assertSent(function ($request) use ($start, $end) {
            if ($request->url() !== 'https://api.openai.com/v1/responses') {
                return false;
            }

            return $request['model'] === 'gpt-5'
                && data_get($request->data(), 'text.format.type') === 'json_schema'
                && data_get($request->data(), 'text.format.strict') === true
                && data_get($request->data(), 'metadata.period_start') === $start->toIso8601String()
                && data_get($request->data(), 'metadata.period_end') === $end->toIso8601String();
        });
    }
}
