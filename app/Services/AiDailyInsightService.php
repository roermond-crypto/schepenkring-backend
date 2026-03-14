<?php

namespace App\Services;

use App\Models\AiDailyInsight;
use App\Models\AuditLog;
use App\Models\PlatformError;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiDailyInsightService
{
    public function generate(array $options = []): AiDailyInsight
    {
        [$start, $end, $timezone] = $this->resolvePeriod($options);

        $payload = [
            'task' => 'Analyze the last 24 hours of audit and error data and produce daily engineering insights.',
            'data' => $this->buildDataPayload($start, $end, $timezone),
        ];

        $insight = AiDailyInsight::query()->firstOrNew([
            'period_start' => $start->utc(),
            'period_end' => $end->utc(),
            'environment' => app()->environment(),
        ]);

        $insight->fill([
            'product' => config('app.name'),
            'timezone' => $timezone,
            'status' => 'running',
            'model' => $this->model(),
            'failure_message' => null,
            'raw_input_json' => $payload,
        ]);
        $insight->save();

        try {
            $analysis = $this->analyze($payload, $start, $end, $timezone);
            $parsed = $analysis['parsed'];

            $insight->fill([
                'status' => 'completed',
                'overall_status' => data_get($parsed, 'summary.overall_status'),
                'headline' => data_get($parsed, 'summary.headline'),
                'summary_json' => data_get($parsed, 'summary'),
                'top_findings_json' => $parsed['top_findings'] ?? [],
                'performance_issues_json' => $parsed['performance_issues'] ?? [],
                'security_signals_json' => $parsed['security_signals'] ?? [],
                'priority_actions_json' => $parsed['priority_actions'] ?? [],
                'raw_output_json' => $analysis['raw'],
                'usage_json' => $analysis['usage'],
                'openai_response_id' => $analysis['response_id'],
                'model' => $analysis['model'],
                'failure_message' => null,
            ]);
            $insight->save();

            return $insight->fresh();
        } catch (Throwable $exception) {
            $insight->fill([
                'status' => 'failed',
                'failure_message' => Str::limit($exception->getMessage(), 2000, ''),
            ]);
            $insight->save();

            throw $exception;
        }
    }

    public function latest(?string $environment = null): ?AiDailyInsight
    {
        return $this->query($environment)
            ->where('status', 'completed')
            ->first();
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = $this->query($filters['environment'] ?? null);

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->where('period_end', '>=', CarbonImmutable::parse((string) $filters['from']));
        }

        if (! empty($filters['to'])) {
            $query->where('period_start', '<=', CarbonImmutable::parse((string) $filters['to']));
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function serialize(AiDailyInsight $insight, bool $includeRaw = false): array
    {
        $data = [
            'id' => $insight->id,
            'period_start' => $insight->period_start?->toIso8601String(),
            'period_end' => $insight->period_end?->toIso8601String(),
            'product' => $insight->product,
            'environment' => $insight->environment,
            'timezone' => $insight->timezone,
            'status' => $insight->status,
            'overall_status' => $insight->overall_status,
            'headline' => $insight->headline,
            'model' => $insight->model,
            'openai_response_id' => $insight->openai_response_id,
            'failure_message' => $insight->failure_message,
            'summary' => $insight->summary_json,
            'top_findings' => $insight->top_findings_json ?? [],
            'performance_issues' => $insight->performance_issues_json ?? [],
            'security_signals' => $insight->security_signals_json ?? [],
            'priority_actions' => $insight->priority_actions_json ?? [],
            'usage' => $insight->usage_json,
            'created_at' => $insight->created_at?->toIso8601String(),
            'updated_at' => $insight->updated_at?->toIso8601String(),
        ];

        if ($includeRaw) {
            $data['raw_input_json'] = $insight->raw_input_json;
            $data['raw_output_json'] = $insight->raw_output_json;
        }

        return $data;
    }

    private function query(?string $environment = null): Builder
    {
        $query = AiDailyInsight::query()->orderByDesc('period_end');

        if ($environment) {
            $query->where('environment', $environment);
        }

        return $query;
    }

    private function analyze(array $payload, CarbonInterface $start, CarbonInterface $end, string $timezone): array
    {
        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI is not configured for daily insights.');
        }

        $response = Http::withToken($apiKey)
            ->timeout((int) config('services.openai.insights_timeout', 90))
            ->post('https://api.openai.com/v1/responses', [
                'model' => $this->model(),
                'store' => false,
                'max_output_tokens' => (int) config('services.openai.insights_max_output_tokens', 2500),
                'reasoning' => [
                    'effort' => (string) config('services.openai.insights_reasoning_effort', 'medium'),
                ],
                'instructions' => $this->instructions(),
                'input' => [[
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'daily_engineering_insights',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
                'metadata' => [
                    'feature' => 'daily_ai_insights',
                    'environment' => app()->environment(),
                    'period_start' => $start->utc()->toIso8601String(),
                    'period_end' => $end->utc()->toIso8601String(),
                    'timezone' => $timezone,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI daily insights request failed: ' . $response->body());
        }

        $body = $response->json();
        $content = $this->extractOutputText((array) ($body['output'] ?? []));
        $parsed = json_decode($content, true);

        if (! is_array($parsed) || ! is_array($parsed['summary'] ?? null)) {
            throw new RuntimeException('OpenAI daily insights response was not valid JSON schema output.');
        }

        return [
            'parsed' => $parsed,
            'raw' => $body,
            'usage' => $body['usage'] ?? null,
            'response_id' => $body['id'] ?? null,
            'model' => $body['model'] ?? $this->model(),
        ];
    }

    private function buildDataPayload(CarbonInterface $start, CarbonInterface $end, string $timezone): array
    {
        $auditQuery = AuditLog::query()->whereBetween('created_at', [$start, $end]);
        $errorQuery = PlatformError::query()->where(function (Builder $builder) use ($start, $end) {
            $builder->whereBetween('last_seen_at', [$start, $end])
                ->orWhereBetween('first_seen_at', [$start, $end]);
        });

        $errors = $errorQuery->orderByDesc('occurrences_count')->get();
        $totalAuditEvents = (clone $auditQuery)->count();
        $totalErrorOccurrences = $errors->sum(fn (PlatformError $error) => max(1, (int) $error->occurrences_count));

        return [
            'metadata' => [
                'product' => config('app.name'),
                'environment' => app()->environment(),
                'period_start' => $start->utc()->toIso8601String(),
                'period_end' => $end->utc()->toIso8601String(),
                'timezone' => $timezone,
            ],
            'aggregate_counters' => [
                'audit_total_events' => $totalAuditEvents,
                'audit_unique_actors' => (clone $auditQuery)->whereNotNull('actor_id')->distinct('actor_id')->count('actor_id'),
                'error_group_count' => $errors->count(),
                'error_occurrence_total' => $totalErrorOccurrences,
                'unresolved_error_groups' => $errors->where('status', 'unresolved')->count(),
                'high_risk_audit_events' => (clone $auditQuery)
                    ->whereIn('risk_level', ['HIGH', 'CRITICAL'])
                    ->count(),
                'active_locations' => (clone $auditQuery)
                    ->whereNotNull('location_id')
                    ->distinct('location_id')
                    ->count('location_id'),
            ],
            'audit_summary' => [
                'total_events' => $totalAuditEvents,
                'event_type_counts' => $this->buildActionCounts($start, $end),
                'role_counts' => $this->buildRoleCounts($start, $end),
                'top_ips' => $this->buildTopIps($start, $end),
                'location_activity' => $this->buildLocationActivity($start, $end),
                'suspicious_patterns_precomputed' => $this->buildSuspiciousPatterns($start, $end),
            ],
            'error_summary' => [
                'total_errors' => $totalErrorOccurrences,
                'error_groups' => $this->buildErrorGroups($errors),
                'slow_routes' => $this->buildSlowRoutes($errors),
            ],
            'samples' => [
                'audit_events' => $this->buildAuditSamples($start, $end),
                'errors' => $this->buildErrorSamples($errors),
            ],
        ];
    }

    private function buildActionCounts(CarbonInterface $start, CarbonInterface $end): array
    {
        return DB::table('audit_logs')
            ->select('action', DB::raw('COUNT(*) as aggregate'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('action')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->action => (int) $row->aggregate])
            ->all();
    }

    private function buildRoleCounts(CarbonInterface $start, CarbonInterface $end): array
    {
        return DB::table('audit_logs')
            ->leftJoin('users as actors', 'actors.id', '=', 'audit_logs.actor_id')
            ->whereBetween('audit_logs.created_at', [$start, $end])
            ->selectRaw("LOWER(COALESCE(actors.type, 'system')) as role, COUNT(*) as aggregate")
            ->groupBy('role')
            ->orderByDesc('aggregate')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->role => (int) $row->aggregate])
            ->all();
    }

    private function buildTopIps(CarbonInterface $start, CarbonInterface $end): array
    {
        return DB::table('audit_logs')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('ip_address')
            ->select('ip_address', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('ip_address')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'ip' => $this->maskIp((string) $row->ip_address),
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();
    }

    private function buildLocationActivity(CarbonInterface $start, CarbonInterface $end): array
    {
        return DB::table('audit_logs')
            ->leftJoin('locations', 'locations.id', '=', 'audit_logs.location_id')
            ->whereBetween('audit_logs.created_at', [$start, $end])
            ->whereNotNull('audit_logs.location_id')
            ->selectRaw('audit_logs.location_id, locations.name, COUNT(*) as aggregate')
            ->groupBy('audit_logs.location_id', 'locations.name')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'location_id' => (int) $row->location_id,
                'name' => $row->name,
                'events' => (int) $row->aggregate,
            ])
            ->values()
            ->all();
    }

    private function buildSuspiciousPatterns(CarbonInterface $start, CarbonInterface $end): array
    {
        $employeeWithoutLocation = DB::table('audit_logs')
            ->join('users as actors', 'actors.id', '=', 'audit_logs.actor_id')
            ->whereBetween('audit_logs.created_at', [$start, $end])
            ->where('actors.type', 'EMPLOYEE')
            ->whereNull('audit_logs.location_id')
            ->where(function ($builder) {
                $builder->where('audit_logs.action', 'like', '%chat%')
                    ->orWhere('audit_logs.action', 'like', '%task%')
                    ->orWhere('audit_logs.action', 'like', '%board%')
                    ->orWhere('audit_logs.meta', 'like', '%chat%')
                    ->orWhere('audit_logs.meta', 'like', '%task%')
                    ->orWhere('audit_logs.meta', 'like', '%board%');
            })
            ->count();

        $sameIpMultipleAccounts = DB::table('audit_logs')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('ip_address')
            ->whereNotNull('actor_id')
            ->groupBy('ip_address')
            ->havingRaw('COUNT(DISTINCT actor_id) > 1')
            ->get()
            ->count();

        $highRiskFailures = DB::table('audit_logs')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('risk_level', ['HIGH', 'CRITICAL'])
            ->where('result', '!=', 'SUCCESS')
            ->count();

        return [
            "employee_without_location_id_attempted_chat_access: {$employeeWithoutLocation}",
            "same_ip_multiple_accounts: {$sameIpMultipleAccounts}",
            "high_risk_failed_audit_events: {$highRiskFailures}",
        ];
    }

    private function buildErrorGroups($errors): array
    {
        return $errors->take(10)->map(function (PlatformError $error) {
            $role = $this->extractErrorRole($error);

            return [
                'fingerprint' => $error->sentry_issue_id ?: $error->reference_code,
                'title' => $this->trimText($error->title ?: $error->message, 240),
                'count' => max(1, (int) $error->occurrences_count),
                'affected_route' => $this->normalizeRoute($error->route),
                'affected_roles' => $role ? [$role] : [],
                'first_seen' => $error->first_seen_at?->utc()->toIso8601String(),
                'last_seen' => $error->last_seen_at?->utc()->toIso8601String(),
            ];
        })->values()->all();
    }

    private function buildSlowRoutes($errors): array
    {
        $buckets = [];

        foreach ($errors as $error) {
            $route = $this->normalizeRoute($error->route);
            $duration = $this->extractDurationMs($error);

            if (! $route || ! $duration) {
                continue;
            }

            $buckets[$route] ??= [];
            $buckets[$route][] = $duration;
        }

        return collect($buckets)
            ->map(function (array $durations, string $route) {
                return [
                    'route' => $route,
                    'avg_ms' => (int) round(array_sum($durations) / count($durations)),
                    'p95_ms' => $this->percentile($durations, 0.95),
                    'count' => count($durations),
                ];
            })
            ->sortByDesc('avg_ms')
            ->take(10)
            ->values()
            ->all();
    }

    private function buildAuditSamples(CarbonInterface $start, CarbonInterface $end): array
    {
        return AuditLog::query()
            ->with('actor:id,type')
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    'timestamp' => $log->created_at?->utc()->toIso8601String(),
                    'action' => $log->action,
                    'user_id' => $log->actor_id,
                    'role' => $log->actor?->type?->value ? strtolower($log->actor->type->value) : null,
                    'location_id' => $log->location_id,
                    'ip' => $this->maskIp($log->ip_address),
                    'route' => data_get($log->meta, 'path'),
                ];
            })
            ->values()
            ->all();
    }

    private function buildErrorSamples($errors): array
    {
        return $errors->sortByDesc('last_seen_at')
            ->take(5)
            ->map(function (PlatformError $error) {
                return [
                    'timestamp' => $error->last_seen_at?->utc()->toIso8601String(),
                    'title' => $this->trimText($error->title ?: $error->message, 240),
                    'route' => $this->normalizeRoute($error->route),
                    'user_id' => $this->extractErrorUserId($error),
                    'role' => $this->extractErrorRole($error),
                    'location_id' => $this->extractErrorLocationId($error),
                ];
            })
            ->values()
            ->all();
    }

    private function instructions(): string
    {
        return <<<'TEXT'
You are a senior product, security, and backend incident analyst.

Analyze the provided daily platform data from audit logs and error summaries.

Your job:
1. Find the most important incidents and patterns.
2. Connect audit behavior with errors when possible.
3. Identify likely root causes.
4. Identify performance issues.
5. Identify security anomalies.
6. Prioritize what the development team should fix first.

Be conservative.
Do not invent facts.
Only use the provided data.
Return valid JSON only.
TEXT;
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'top_findings', 'performance_issues', 'security_signals', 'priority_actions'],
            'properties' => [
                'summary' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['overall_status', 'headline'],
                    'properties' => [
                        'overall_status' => [
                            'type' => 'string',
                            'enum' => ['ok', 'info', 'warning', 'critical'],
                            'description' => 'Overall engineering health for the period.',
                        ],
                        'headline' => [
                            'type' => 'string',
                            'description' => 'A concise one-sentence summary of the most important issue.',
                        ],
                    ],
                ],
                'top_findings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['severity', 'type', 'title', 'evidence', 'likely_root_cause', 'recommended_action'],
                        'properties' => [
                            'severity' => [
                                'type' => 'string',
                                'enum' => ['low', 'medium', 'high', 'critical'],
                            ],
                            'type' => [
                                'type' => 'string',
                                'enum' => ['bug', 'performance', 'security', 'data_quality', 'process'],
                            ],
                            'title' => ['type' => 'string'],
                            'evidence' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'likely_root_cause' => ['type' => 'string'],
                            'recommended_action' => ['type' => 'string'],
                        ],
                    ],
                ],
                'performance_issues' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['severity', 'route', 'problem', 'evidence', 'recommended_action'],
                        'properties' => [
                            'severity' => [
                                'type' => 'string',
                                'enum' => ['low', 'medium', 'high', 'critical'],
                            ],
                            'route' => ['type' => 'string'],
                            'problem' => ['type' => 'string'],
                            'evidence' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['avg_ms', 'p95_ms', 'count'],
                                'properties' => [
                                    'avg_ms' => ['type' => 'integer'],
                                    'p95_ms' => ['type' => 'integer'],
                                    'count' => ['type' => 'integer'],
                                ],
                            ],
                            'recommended_action' => ['type' => 'string'],
                        ],
                    ],
                ],
                'security_signals' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['severity', 'title', 'recommended_action'],
                        'properties' => [
                            'severity' => [
                                'type' => 'string',
                                'enum' => ['low', 'medium', 'high', 'critical'],
                            ],
                            'title' => ['type' => 'string'],
                            'recommended_action' => ['type' => 'string'],
                        ],
                    ],
                ],
                'priority_actions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }

    private function extractOutputText(array $output): string
    {
        $parts = [];

        foreach ($output as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function resolvePeriod(array $options): array
    {
        $timezone = (string) ($options['timezone'] ?? config('app.timezone', 'UTC'));

        $end = ! empty($options['end'])
            ? CarbonImmutable::parse((string) $options['end'], $timezone)
            : CarbonImmutable::now($timezone);

        $start = ! empty($options['start'])
            ? CarbonImmutable::parse((string) $options['start'], $timezone)
            : $end->subDay();

        if ($start->greaterThanOrEqualTo($end)) {
            throw new RuntimeException('The daily insights start time must be before the end time.');
        }

        return [$start, $end, $timezone];
    }

    private function model(): string
    {
        return (string) config('services.openai.insights_model', 'gpt-5');
    }

    private function maskIp(?string $ip): ?string
    {
        if (! is_string($ip) || trim($ip) === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = 'x';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, 4)) . ':x:x:x:x';
        }

        return null;
    }

    private function normalizeRoute(?string $route): ?string
    {
        if (! is_string($route) || trim($route) === '') {
            return null;
        }

        $path = parse_url($route, PHP_URL_PATH);

        return $this->trimText($path ?: $route, 255);
    }

    private function trimText(?string $value, int $limit): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(Str::squish($value), $limit, '...');
    }

    private function extractErrorRole(PlatformError $error): ?string
    {
        $role = Arr::get($error->tags, 'role')
            ?? Arr::get($error->last_event_sample_json, 'tags.role')
            ?? Arr::get($error->last_event_sample_json, 'user.role');

        return is_string($role) && trim($role) !== '' ? strtolower(trim($role)) : null;
    }

    private function extractErrorUserId(PlatformError $error): ?int
    {
        $userId = Arr::get($error->tags, 'user_id')
            ?? Arr::get($error->last_event_sample_json, 'tags.user_id')
            ?? Arr::get($error->last_event_sample_json, 'user.id');

        return is_numeric($userId) ? (int) $userId : null;
    }

    private function extractErrorLocationId(PlatformError $error): ?int
    {
        $locationId = Arr::get($error->tags, 'harbor_id')
            ?? Arr::get($error->tags, 'location_id')
            ?? Arr::get($error->last_event_sample_json, 'tags.harbor_id')
            ?? Arr::get($error->last_event_sample_json, 'tags.location_id');

        return is_numeric($locationId) ? (int) $locationId : null;
    }

    private function extractDurationMs(PlatformError $error): ?int
    {
        $traceData = Arr::get($error->last_event_sample_json, 'contexts.trace.data', []);
        $measurements = Arr::get($error->last_event_sample_json, 'measurements', []);

        $candidates = [
            is_array($traceData) ? ($traceData['transaction.duration'] ?? null) : null,
            is_array($traceData) ? ($traceData['transaction.duration_ms'] ?? null) : null,
            Arr::get($traceData, 'transaction.duration'),
            Arr::get($traceData, 'transaction.duration_ms'),
            Arr::get($error->last_event_sample_json, 'contexts.trace.duration'),
            Arr::get($measurements, 'lcp.value'),
            Arr::get($measurements, 'fp.value'),
            Arr::get($error->last_event_sample_json, 'extra.duration_ms'),
            Arr::get($error->last_event_sample_json, 'duration'),
        ];

        foreach ($candidates as $value) {
            if (is_numeric($value) && (float) $value > 0) {
                return (int) round((float) $value);
            }
        }

        return null;
    }

    private function percentile(array $values, float $percentile): int
    {
        sort($values);

        $index = (int) ceil((count($values) - 1) * $percentile);

        return (int) round($values[$index] ?? end($values) ?: 0);
    }
}
