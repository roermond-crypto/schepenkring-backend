<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CopilotAction;
use App\Models\CopilotActionPhrase;
use App\Models\CopilotActionSuggestion;
use App\Models\CopilotAuditEvent;
use App\Models\User;
use App\Support\CopilotLanguage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CopilotLearningService
{
    public function __construct(
        private CopilotFuzzyMatcher $matcher,
        private CopilotLanguage $language,
        private CopilotMemoryService $memory,
        private CopilotSuggestionAiService $suggestionAi
    ) {
    }

    public function ingestAuditLog(AuditLog $log): void
    {
        if (! $this->learningEnabled() || $this->shouldIgnoreAudit($log)) {
            return;
        }

        $this->memory->rememberAuditLog($log);
        $this->refreshSuggestionsIfDue();
    }

    public function ingestCopilotAuditEvent(CopilotAuditEvent $event): void
    {
        if (! $this->learningEnabled() || in_array($event->source, ['learning'], true)) {
            return;
        }

        $this->memory->rememberCopilotEvent($event);
        $this->refreshSuggestionsIfDue();
    }

    public function syncAction(CopilotAction $action): void
    {
        $this->memory->rememberAction($action);
    }

    public function syncSuggestion(CopilotActionSuggestion $suggestion): void
    {
        $this->memory->rememberSuggestion($suggestion);
    }

    /**
     * @return array{count:int,data:\Illuminate\Support\Collection<int, CopilotActionSuggestion>}
     */
    public function mineFromHistory(?int $days = null, bool $autoCreate = false): array
    {
        if (! $this->learningEnabled()) {
            return [
                'count' => 0,
                'data' => collect(),
            ];
        }

        $days = $days ?: (int) config('copilot.learning.lookback_days', 30);
        $after = now()->subDays($days);

        $auditLogs = AuditLog::query()
            ->where('created_at', '>=', $after)
            ->orderByDesc('id')
            ->get();

        $copilotEvents = CopilotAuditEvent::query()
            ->where('created_at', '>=', $after)
            ->where('source', '!=', 'learning')
            ->whereNotNull('input_text')
            ->orderByDesc('id')
            ->get();

        $candidates = collect()
            ->merge($this->buildPhraseSuggestions($copilotEvents))
            ->merge($this->buildRouteSuggestions($auditLogs))
            ->unique('suggestion_key')
            ->values();

        $suggestions = collect();

        foreach ($candidates as $candidate) {
            $suggestion = $this->upsertSuggestion($candidate);
            if (! $suggestion) {
                continue;
            }

            $shouldAutoCreate = $autoCreate || (
                (bool) config('copilot.learning.auto_create_enabled', false)
                && (float) $suggestion->confidence >= (float) config('copilot.learning.auto_create_threshold', 0.9)
                && $suggestion->status === 'pending'
            );

            if ($shouldAutoCreate) {
                $suggestion = $this->approveSuggestion($suggestion, null, [], true);
            }

            $suggestions->push($suggestion->fresh(['targetAction', 'createdAction', 'reviewer']));
        }

        return [
            'count' => $suggestions->count(),
            'data' => $suggestions,
        ];
    }

    public function approveSuggestion(
        CopilotActionSuggestion $suggestion,
        ?User $reviewer,
        array $overrides = [],
        bool $autoCreated = false
    ): CopilotActionSuggestion {
        return DB::transaction(function () use ($suggestion, $reviewer, $overrides, $autoCreated) {
            $suggestion->fill($this->filterSuggestionFields($overrides));
            $suggestion->save();

            $action = $suggestion->targetAction;

            if (! $action) {
                $actionPayload = [
                    'action_id' => $suggestion->action_id ?: $this->buildFallbackActionId($suggestion->module, $suggestion->title),
                    'title' => $suggestion->title,
                    'short_description' => $suggestion->short_description,
                    'module' => $suggestion->module,
                    'description' => $suggestion->description,
                    'route_template' => $suggestion->route_template ?: '/',
                    'query_template' => $suggestion->query_template,
                    'required_params' => $suggestion->required_params,
                    'input_schema' => $suggestion->input_schema,
                    'example_prompts' => $suggestion->example_prompts,
                    'permission_key' => $suggestion->permission_key,
                    'required_role' => $suggestion->required_role,
                    'risk_level' => $suggestion->risk_level ?: 'low',
                    'confirmation_required' => (bool) $suggestion->confirmation_required,
                    'enabled' => true,
                    'created_by' => $reviewer?->id,
                    'updated_by' => $reviewer?->id,
                ];

                $action = CopilotAction::query()->firstOrCreate(
                    ['action_id' => $actionPayload['action_id']],
                    $actionPayload
                );

                if (! $action->wasRecentlyCreated) {
                    $action->fill(array_filter($actionPayload, static fn ($value) => $value !== null));
                    $action->updated_by = $reviewer?->id;
                    $action->save();
                }
            }

            foreach ($this->normalizeSuggestedPhrases($suggestion->phrases) as $phrase) {
                $exists = CopilotActionPhrase::query()
                    ->where('copilot_action_id', $action->id)
                    ->where('phrase', $phrase['phrase'])
                    ->where('language', $phrase['language'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                CopilotActionPhrase::create([
                    'copilot_action_id' => $action->id,
                    'phrase' => $phrase['phrase'],
                    'language' => $phrase['language'],
                    'priority' => $phrase['priority'],
                    'enabled' => true,
                    'created_by' => $reviewer?->id,
                    'updated_by' => $reviewer?->id,
                ]);
            }

            $suggestion->created_action_id = $action->id;
            $suggestion->status = $autoCreated ? 'auto_created' : 'approved';
            $suggestion->reviewed_by = $reviewer?->id;
            $suggestion->reviewed_at = now();
            $suggestion->save();

            $this->syncAction($action);
            $this->syncSuggestion($suggestion);
            $this->logLearningEvent('approved', $suggestion, [
                'auto_created' => $autoCreated,
                'created_action_id' => $action->id,
            ]);

            return $suggestion;
        });
    }

    public function disableSuggestion(CopilotActionSuggestion $suggestion, ?User $reviewer = null, string $status = 'disabled'): CopilotActionSuggestion
    {
        $suggestion->status = $status;
        $suggestion->reviewed_by = $reviewer?->id;
        $suggestion->reviewed_at = now();
        $suggestion->save();
        $this->syncSuggestion($suggestion);

        $this->logLearningEvent('status_changed', $suggestion, [
            'status' => $status,
        ]);

        return $suggestion;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPhraseSuggestions(Collection $events): array
    {
        $minOccurrences = (int) config('copilot.learning.min_occurrences', 3);
        $successful = $events->filter(fn (CopilotAuditEvent $event) => ! empty($event->selected_action_id));
        $failures = $events->filter(function (CopilotAuditEvent $event) {
            return ($event->status === 'no_match' || $event->failure_reason === 'no_action')
                && ! empty(trim((string) $event->input_text));
        });

        $suggestions = [];

        foreach ($failures->groupBy(fn (CopilotAuditEvent $event) => $this->matcher->normalize((string) $event->input_text)) as $normalized => $group) {
            if ($normalized === '' || $group->count() < $minOccurrences) {
                continue;
            }

            $action = $this->resolveTargetActionFromHistory($normalized, $successful);
            if (! $action) {
                $action = $this->resolveTargetActionFromMemory((string) $group->first()->input_text);
            }
            if (! $action) {
                continue;
            }

            $phrases = $group->pluck('input_text')
                ->filter()
                ->unique()
                ->map(function (?string $phrase) use ($group) {
                    $language = $this->language->resolve((string) $phrase)['language'];

                    return [
                        'phrase' => trim((string) $phrase),
                        'language' => $language,
                        'priority' => min(100, 20 + ($group->count() * 10)),
                    ];
                })
                ->values()
                ->all();

            $memoryMatches = $this->memory->searchSimilar((string) $group->first()->input_text, (int) config('copilot.learning.memory_top_k', 5));
            $candidate = [
                'suggestion_key' => sha1('phrase|' . $action->id . '|' . $normalized),
                'suggestion_type' => 'phrase',
                'target_copilot_action_id' => $action->id,
                'action_id' => $action->action_id,
                'title' => 'Add trigger phrases for ' . $action->title,
                'short_description' => 'Repeated copilot misses match an existing action and should become supported triggers.',
                'module' => $action->module,
                'description' => 'Learned from repeated copilot misses and similar successful actions.',
                'route_template' => $action->route_template,
                'query_template' => $action->query_template,
                'required_params' => $action->required_params ?? [],
                'input_schema' => $action->input_schema,
                'phrases' => $phrases,
                'example_prompts' => collect($phrases)->pluck('phrase')->take(3)->values()->all(),
                'permission_key' => $action->permission_key,
                'required_role' => $action->required_role,
                'risk_level' => $action->risk_level ?: 'low',
                'confirmation_required' => (bool) $action->confirmation_required,
                'confidence' => min(0.95, 0.55 + ($group->count() * 0.08)),
                'evidence_count' => $group->count(),
                'evidence' => [
                    'copilot_event_ids' => $group->pluck('id')->values()->all(),
                    'sample_inputs' => collect($phrases)->pluck('phrase')->all(),
                    'matched_action_id' => $action->action_id,
                ],
                'pinecone_matches' => $memoryMatches,
                'reasoning' => 'Repeated failed copilot prompts closely resemble an existing approved action.',
            ];

            $suggestions[] = $this->refineCandidate($candidate);
        }

        return $suggestions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRouteSuggestions(Collection $auditLogs): array
    {
        $minOccurrences = (int) config('copilot.learning.min_occurrences', 3);

        $candidates = $auditLogs
            ->map(function (AuditLog $log) {
                $insight = $this->deriveRouteInsight($log);
                if (! $insight) {
                    return null;
                }

                return [
                    'log' => $log,
                    'insight' => $insight,
                ];
            })
            ->filter()
            ->groupBy(fn (array $item) => $item['insight']['signature']);

        $suggestions = [];

        foreach ($candidates as $group) {
            if ($group->count() < $minOccurrences) {
                continue;
            }

            $insight = $group->first()['insight'];
            $existingAction = CopilotAction::query()
                ->where('route_template', $insight['route_template'])
                ->orWhere('action_id', $insight['action_id'])
                ->first();

            $phrases = collect($this->defaultPhrasesForInsight($insight))
                ->map(fn (string $phrase) => [
                    'phrase' => $phrase,
                    'language' => 'en',
                    'priority' => min(100, 10 + ($group->count() * 5)),
                ])
                ->values()
                ->all();

            $memoryMatches = $this->memory->searchSimilar($insight['title'] . ' ' . implode(' ', array_column($phrases, 'phrase')), (int) config('copilot.learning.memory_top_k', 5));

            $candidate = [
                'suggestion_key' => sha1(($existingAction ? 'phrase' : 'action') . '|' . $insight['route_template']),
                'suggestion_type' => $existingAction ? 'phrase' : 'action',
                'target_copilot_action_id' => $existingAction?->id,
                'action_id' => $existingAction?->action_id ?: $insight['action_id'],
                'title' => $existingAction ? 'Add triggers for ' . $existingAction->title : $insight['title'],
                'short_description' => $existingAction
                    ? 'Repeated audited platform behavior indicates missing trigger phrases for an existing action.'
                    : 'Repeated audited platform behavior indicates this route should be a copilot action.',
                'module' => $existingAction?->module ?: $insight['module'],
                'description' => 'Generated from repeated audit log patterns on the same platform route.',
                'route_template' => $existingAction?->route_template ?: $insight['route_template'],
                'query_template' => $existingAction?->query_template,
                'required_params' => $existingAction?->required_params ?? $insight['required_params'],
                'input_schema' => $existingAction?->input_schema,
                'phrases' => $phrases,
                'example_prompts' => array_column($phrases, 'phrase'),
                'permission_key' => $existingAction?->permission_key,
                'required_role' => $existingAction?->required_role ?: $insight['required_role'],
                'risk_level' => $existingAction?->risk_level ?: 'low',
                'confirmation_required' => (bool) ($existingAction?->confirmation_required ?? false),
                'confidence' => min(0.96, 0.5 + ($group->count() * 0.07) + ($existingAction ? 0.08 : 0.12)),
                'evidence_count' => $group->count(),
                'evidence' => [
                    'audit_log_ids' => $group->pluck('log.id')->values()->all(),
                    'sample_paths' => $group->pluck('insight.original_path')->unique()->take(5)->values()->all(),
                    'sample_actions' => $group->pluck('log.action')->unique()->take(5)->values()->all(),
                ],
                'pinecone_matches' => $memoryMatches,
                'reasoning' => 'Multiple audited requests follow the same route pattern, which should be available to copilot.',
            ];

            $suggestions[] = $this->refineCandidate($candidate);
        }

        return $suggestions;
    }

    private function resolveTargetActionFromHistory(string $normalizedInput, Collection $successful): ?CopilotAction
    {
        $bestActionId = null;
        $bestScore = 0.0;

        foreach ($successful as $event) {
            $candidateInput = $this->matcher->normalize((string) ($event->input_text ?? ''));
            if ($candidateInput === '') {
                continue;
            }

            $score = $this->matcher->score($normalizedInput, $candidateInput);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestActionId = $event->selected_action_id ?: $event->user_correction_action_id;
            }
        }

        if (! $bestActionId || $bestScore < 0.6) {
            return null;
        }

        return CopilotAction::query()
            ->where('action_id', $bestActionId)
            ->where('enabled', true)
            ->first();
    }

    private function resolveTargetActionFromMemory(string $input): ?CopilotAction
    {
        foreach ($this->memory->searchSimilar($input, (int) config('copilot.learning.memory_top_k', 5)) as $match) {
            $actionId = $match['metadata']['action_id'] ?? null;
            if (! $actionId) {
                continue;
            }

            $action = CopilotAction::query()
                ->where('action_id', $actionId)
                ->where('enabled', true)
                ->first();

            if ($action) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function deriveRouteInsight(AuditLog $log): ?array
    {
        $path = trim((string) (($log->meta ?? [])['path'] ?? ''), '/');
        if ($path === '' || ! str_starts_with($path, 'api/')) {
            return null;
        }

        if ($this->shouldIgnorePath($path)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', substr($path, 4))));
        if (empty($segments)) {
            return null;
        }

        $frontendSegments = [];
        $requiredParams = [];
        $resourceName = null;
        $hasIdentifier = false;

        foreach ($segments as $index => $segment) {
            if ($this->isIdentifierSegment($segment)) {
                $previous = $segments[$index - 1] ?? 'item';
                $parameter = $this->parameterNameForResource($previous);
                $frontendSegments[] = '{' . $parameter . '}';
                $requiredParams[] = $parameter;
                $resourceName = $previous;
                $hasIdentifier = true;
                continue;
            }

            $frontendSegments[] = $segment;
            $resourceName = $segment;
        }

        if (! $resourceName) {
            return null;
        }

        $module = $segments[0] === 'admin'
            ? ($segments[1] ?? $resourceName)
            : $segments[0];

        $resourceWords = str_replace('-', ' ', $resourceName);
        $resourceSingular = Str::snake(Str::singular($resourceWords));
        $routeTemplate = '/' . implode('/', $frontendSegments);
        $mode = $hasIdentifier ? 'view' : 'list';
        $actionId = $resourceSingular . '.' . $mode;

        return [
            'signature' => $routeTemplate,
            'original_path' => $path,
            'module' => str_replace('-', '_', $module),
            'action_id' => $actionId,
            'title' => ($hasIdentifier ? 'Open ' : 'List ') . str_replace('_', ' ', $hasIdentifier ? $resourceSingular : Str::snake($resourceWords)),
            'route_template' => $routeTemplate,
            'required_params' => array_values(array_unique($requiredParams)),
            'required_role' => $segments[0] === 'admin' ? 'admin' : null,
            'mode' => $mode,
            'resource' => $resourceSingular,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function defaultPhrasesForInsight(array $insight): array
    {
        $resource = str_replace('_', ' ', (string) $insight['resource']);
        $pluralResource = Str::plural($resource);

        if (($insight['mode'] ?? null) === 'view') {
            return [
                "open {$resource}",
                "show {$resource}",
                "view {$resource}",
            ];
        }

        return [
            "open {$pluralResource}",
            "show {$pluralResource}",
            "list {$pluralResource}",
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function refineCandidate(array $candidate): array
    {
        $refined = $this->suggestionAi->suggest($candidate);
        if (! $refined) {
            return $candidate;
        }

        return array_merge($candidate, array_filter($refined, static fn ($value) => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function upsertSuggestion(array $candidate): ?CopilotActionSuggestion
    {
        if (empty($candidate['suggestion_key']) || empty($candidate['title'])) {
            return null;
        }

        $suggestion = CopilotActionSuggestion::query()->firstOrNew([
            'suggestion_key' => $candidate['suggestion_key'],
        ]);

        $originalStatus = $suggestion->status;
        $suggestion->fill($this->filterSuggestionFields($candidate));
        if (! $suggestion->exists) {
            $suggestion->status = 'pending';
        } elseif (! in_array((string) $originalStatus, ['approved', 'auto_created', 'disabled', 'rejected'], true)) {
            $suggestion->status = 'pending';
        }

        $suggestion->save();
        $this->syncSuggestion($suggestion);
        $this->logLearningEvent($suggestion->wasRecentlyCreated ? 'created' : 'updated', $suggestion, [
            'suggestion_type' => $suggestion->suggestion_type,
            'confidence' => $suggestion->confidence,
            'evidence_count' => $suggestion->evidence_count,
        ]);

        return $suggestion;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterSuggestionFields(array $payload): array
    {
        return array_intersect_key($payload, array_flip([
            'suggestion_type',
            'target_copilot_action_id',
            'created_action_id',
            'action_id',
            'title',
            'short_description',
            'module',
            'description',
            'route_template',
            'query_template',
            'required_params',
            'input_schema',
            'phrases',
            'example_prompts',
            'permission_key',
            'required_role',
            'risk_level',
            'confirmation_required',
            'confidence',
            'evidence_count',
            'evidence',
            'pinecone_matches',
            'reasoning',
            'status',
            'reviewed_by',
            'reviewed_at',
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $phrases
     * @return array<int, array{phrase:string,language:?string,priority:int}>
     */
    private function normalizeSuggestedPhrases(?array $phrases): array
    {
        return collect($phrases ?? [])
            ->map(function ($phrase) {
                $text = trim((string) ($phrase['phrase'] ?? ''));
                if ($text === '') {
                    return null;
                }

                return [
                    'phrase' => $text,
                    'language' => $this->language->normalize($phrase['language'] ?? null),
                    'priority' => max(0, min(100, (int) ($phrase['priority'] ?? 50))),
                ];
            })
            ->filter()
            ->unique(fn (array $phrase) => $phrase['phrase'] . '|' . ($phrase['language'] ?? ''))
            ->values()
            ->all();
    }

    private function buildFallbackActionId(?string $module, string $title): string
    {
        $base = $module ?: Str::slug($title, '_');

        return Str::slug($base, '_') . '.view';
    }

    private function parameterNameForResource(string $resource): string
    {
        return Str::snake(Str::singular(str_replace('-', '_', $resource))) . '_id';
    }

    private function isIdentifierSegment(string $segment): bool
    {
        return is_numeric($segment)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f-]{27,}$/i', $segment) === 1;
    }

    private function shouldIgnoreAudit(AuditLog $log): bool
    {
        if (str_starts_with((string) $log->action, 'copilot.')) {
            return true;
        }

        $path = trim((string) (($log->meta ?? [])['path'] ?? ''), '/');

        return $path !== '' && $this->shouldIgnorePath($path);
    }

    private function shouldIgnorePath(string $path): bool
    {
        foreach ([
            'api/copilot',
            'api/admin/copilot',
            'api/audit',
            'api/audit-logs',
            'api/admin/audit',
            'api/errors',
            'api/admin/errors',
            'api/webhooks',
            'api/internal',
            'api/analytics',
            'api/auth',
            'api/social',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function refreshSuggestionsIfDue(): void
    {
        $interval = max(30, (int) config('copilot.learning.refresh_interval_seconds', 300));
        $cacheKey = 'copilot:learning:refresh-lock';

        if (! Cache::add($cacheKey, now()->timestamp, $interval)) {
            return;
        }

        $this->mineFromHistory();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function logLearningEvent(string $status, CopilotActionSuggestion $suggestion, array $meta = []): void
    {
        $userId = $suggestion->reviewed_by ?: User::query()->value('id');
        if (! $userId) {
            return;
        }

        CopilotAuditEvent::create([
            'user_id' => $userId,
            'source' => 'learning',
            'stage' => 'learn',
            'input_text' => $suggestion->title,
            'resolved_action_candidates' => null,
            'selected_action_id' => $suggestion->action_id,
            'selected_action_params' => [
                'suggestion_id' => $suggestion->id,
                'suggestion_type' => $suggestion->suggestion_type,
            ],
            'confidence' => $suggestion->confidence,
            'status' => $status,
            'matching_detail' => [
                'suggestion_id' => $suggestion->id,
                'route_template' => $suggestion->route_template,
                'evidence' => $suggestion->evidence,
                'meta' => $meta,
            ],
            'created_at' => now(),
        ]);
    }

    private function learningEnabled(): bool
    {
        return (bool) config('copilot.learning.enabled', true);
    }
}
