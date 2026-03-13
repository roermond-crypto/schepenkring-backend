<?php

namespace App\Services;

use App\Models\CopilotAction;
use App\Models\CopilotActionPhrase;
use App\Models\Boat;
use App\Models\Location;
use App\Models\User;
use App\Support\CopilotLanguage;

class CopilotResolverService
{
    public function __construct(
        private CopilotSearchService $searchService,
        private CopilotPermissionService $permissionService,
        private CopilotFuzzyMatcher $matcher,
        private CopilotAiRouterService $aiRouter,
        private CopilotFaqService $faqService,
        private CopilotLanguage $language,
        private LocationAccessService $locationAccess,
        private PineconeMatcherService $pineconeMatcher
    ) {
    }

    public function resolve(string $input, User $user, array $context = []): array
    {
        $input = trim($input);
        $language = $context['language'] ?? null;
        $source = $context['source'] ?? null;

        $actions = [];
        $results = [];
        $answers = [];
        $clarifying = null;
        $needsConfirmation = false;
        $confidence = 0.0;

        if ($input === '') {
            return compact('actions', 'results', 'answers', 'clarifying', 'needsConfirmation', 'confidence');
        }

        $deterministic = $this->resolveDeterministic($input, $user);
        if ($deterministic) {
            $actions = [$deterministic['action']];
            $confidence = 1.0;
            $needsConfirmation = $deterministic['action']['confirmation_required'] ?? false;
        } else {
            $actionCandidates = $this->resolveByPhrases($input, $user, $language, $context);

            if ($this->shouldCallAi($input, $actionCandidates)) {
                $aiResult = $this->aiRouter->route($input, $actionCandidates, $context);
                if ($aiResult) {
                    $validated = $this->validateAiResult($aiResult, $user);
                    if ($validated) {
                        $actions = [$validated];
                        $confidence = (float) ($aiResult['confidence'] ?? 0.6);
                        $needsConfirmation = $validated['confirmation_required'] ?? false;
                        $clarifying = $aiResult['clarifying_question'] ?? null;
                    }
                }
            }

            if (empty($actions)) {
                $actions = $this->buildActionsFromCandidates($actionCandidates, $input, $user, $clarifying, $language);
                if (!empty($actions)) {
                    $confidence = (float) ($actions[0]['score'] ?? 0.4);
                }
            }
        }

        $results = $this->searchService->search($input, $user, (int) config('copilot.fuzzy_limit', 8));
        $results = $this->attachDeeplinks($results);

        $knowledge = $this->buildAnswers($input, $this->faqLocationScope($user, $context), $context);
        $answers = $knowledge['answers'];
        $confidence = max($confidence, (float) ($knowledge['confidence'] ?? 0.0));

        if (empty($actions) && empty($results) && empty($answers)) {
            $clarifying = $clarifying ?: $this->copilotLanguage()->translate('clarify_open_or_search', (string) $language);
        }

        $needsConfirmation = $needsConfirmation || $this->anyNeedsConfirmation($actions);

        return [
            'actions' => $actions,
            'results' => $results,
            'answers' => $answers,
            'clarifying_question' => $clarifying,
            'needs_confirmation' => $needsConfirmation,
            'confidence' => round($confidence, 3),
            'source' => $source,
            'answer_strategy' => $knowledge['strategy'] ?? null,
            'knowledge_trace' => $knowledge['trace'] ?? null,
        ];
    }

    private function resolveDeterministic(string $input, User $user): ?array
    {
        $patterns = [
            'invoice' => '/\b(invoice|factuur|rechnung)\s*#?\s*(\d+)\b/i',
            'boat' => '/\b(boat|boot|yacht)\s*#?\s*(\d+)\b/i',
            'harbor' => '/\b(harbor|haven|partner)\s*#?\s*(\d+)\b/i',
            'user' => '/\b(user|gebruiker)\s*#?\s*(\d+)\b/i',
            'payment' => '/\b(payment|betaling|mollie)\s*#?\s*(\d+)\b/i',
            'deal' => '/\b(deal|transaction|escrow)\s*#?\s*(\d+)\b/i',
        ];

        foreach ($patterns as $type => $regex) {
            if (preg_match($regex, $input, $matches)) {
                $id = (int) $matches[2];
                $entity = $this->findEntity($type, $id, $user);
                if (!$entity) {
                    return null;
                }

                $actionId = config('copilot.default_action_map.' . $type);
                $action = $actionId ? $this->actionFromCatalog($actionId, [$type . '_id' => $id], $user) : null;

                if ($action) {
                    return ['action' => $action];
                }

                $routeTemplate = config('copilot.search_routes.' . $type);
                if ($routeTemplate) {
                    return [
                        'action' => [
                            'action_id' => $actionId ?? ($type . '.view'),
                            'title' => ucfirst($type) . ' #' . $id,
                            'deeplink' => $this->applyTemplate($routeTemplate, ['id' => $id, $type . '_id' => $id]),
                            'reason' => 'Matched ID pattern',
                            'confirmation_required' => false,
                            'risk_level' => 'low',
                        ],
                    ];
                }
            }
        }

        return null;
    }

    private function resolveByPhrases(string $input, User $user, ?string $language, array $context): array
    {
        $normalizedInput = $this->matcher->normalize($input);
        $query = CopilotActionPhrase::query()
            ->where('enabled', true)
            ->with('action');

        if ($language) {
            $query->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            });
        }

        $phrases = $query->get();
        $candidates = [];

        foreach ($phrases as $phrase) {
            if (!$phrase->action || !$phrase->action->enabled) {
                continue;
            }

            if (! $this->permissionService->canUseAction(
                $user,
                $phrase->action->permission_key,
                $phrase->action->required_role
            )) {
                continue;
            }

            $normalizedPhrase = $this->matcher->normalize($phrase->phrase);
            if ($normalizedPhrase === '') {
                continue;
            }

            $score = 0.0;
            if (str_contains($normalizedInput, $normalizedPhrase)) {
                $score = 0.85;
            } else {
                $score = $this->matcher->score($normalizedInput, $normalizedPhrase) * 0.8;
            }

            if ($phrase->priority > 0) {
                $score += min(0.1, $phrase->priority / 100);
            }

            if (!empty($context['module']) && $phrase->action->module === $context['module']) {
                $score += 0.05;
            }

            if ($score < 0.35) {
                continue;
            }

            $candidates[] = [
                'action_id' => $phrase->action->action_id,
                'title' => $phrase->action->title,
                'required_params' => $phrase->action->required_params ?? [],
                'score' => round(min(1.0, $score), 3),
                'reason' => 'Matched phrase: ' . $phrase->phrase,
            ];
        }

        usort($candidates, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($candidates, 0, 5);
    }

    private function shouldCallAi(string $input, array $candidates): bool
    {
        if (!config('copilot.ai_enabled')) {
            return false;
        }

        if (count($candidates) > 1) {
            $top = $candidates[0]['score'] ?? 0.0;
            $second = $candidates[1]['score'] ?? 0.0;
            return abs($top - $second) < 0.1;
        }

        return $this->looksComplex($input);
    }

    private function looksComplex(string $input): bool
    {
        $input = strtolower($input);
        return str_contains($input, ' and ') || str_contains($input, 'latest') || str_contains($input, 'last') || str_contains($input, 'open the');
    }

    private function validateAiResult(array $aiResult, User $user): ?array
    {
        $actionId = $aiResult['action_id'] ?? null;
        if (!$actionId) {
            return null;
        }

        $params = is_array($aiResult['params'] ?? null) ? $aiResult['params'] : [];
        return $this->actionFromCatalog($actionId, $params, $user);
    }

    private function buildActionsFromCandidates(array $candidates, string $input, User $user, ?string &$clarifying, ?string $language = null): array
    {
        $actions = [];
        foreach ($candidates as $candidate) {
            $params = $this->extractParams($input, $candidate['required_params'] ?? []);
            $params = $this->fillMissingParamsFromSearch($input, $candidate['required_params'] ?? [], $params, $user);

            $action = $this->actionFromCatalog($candidate['action_id'], $params, $user);
            if (!$action) {
                continue;
            }
            $action['score'] = $candidate['score'] ?? null;
            $action['reason'] = $candidate['reason'] ?? null;
            $actions[] = $action;
        }

        if (count($actions) > 1 && !$clarifying) {
            $clarifying = $this->copilotLanguage()->translate('clarify_action', (string) $language);
        }

        return $actions;
    }

    private function actionFromCatalog(string $actionId, array $params, User $user): ?array
    {
        $action = CopilotAction::query()
            ->where('action_id', $actionId)
            ->where('enabled', true)
            ->first();

        if (!$action) {
            return null;
        }

        if (! $this->permissionService->canUseAction($user, $action->permission_key, $action->required_role)) {
            return null;
        }

        $required = $action->required_params ?? [];
        foreach ($required as $param) {
            if (!array_key_exists($param, $params)) {
                return null;
            }
        }

        if (!$this->validateParamsExist($params, $user)) {
            return null;
        }

        $deeplink = $this->applyTemplate($action->route_template, $params);

        return [
            'action_id' => $action->action_id,
            'title' => $action->title,
            'deeplink' => $deeplink,
            'risk_level' => $action->risk_level,
            'confirmation_required' => (bool) $action->confirmation_required,
            'params' => $params,
        ];
    }

    private function validateParamsExist(array $params, User $user): bool
    {
        foreach ($params as $key => $value) {
            if (!str_ends_with($key, '_id')) {
                continue;
            }
            if (!is_numeric($value)) {
                continue;
            }
            $id = (int) $value;
            switch ($key) {
                case 'boat_id':
                case 'yacht_id':
                    if (! $this->boatExistsForUser($id, $user)) {
                        return false;
                    }
                    break;
                case 'harbor_id':
                case 'location_id':
                    if (! $this->locationExistsForUser($id, $user)) {
                        return false;
                    }
                    break;
                case 'user_id':
                    if (! $this->userExistsForUser($id, $user)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    private function extractParams(string $input, array $requiredParams): array
    {
        $params = [];
        foreach ($requiredParams as $param) {
            if (str_ends_with($param, '_id')) {
                $id = $this->extractNumericId($input);
                if ($id !== null) {
                    $params[$param] = $id;
                }
            }
        }

        return array_filter($params, fn ($value) => $value !== null);
    }

    private function findEntity(string $type, int $id, User $user): bool
    {
        return match ($type) {
            'boat' => $this->boatExistsForUser($id, $user),
            'harbor' => $this->locationExistsForUser($id, $user),
            'user' => $this->userExistsForUser($id, $user),
            default => false,
        };
    }

    private function applyTemplate(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    private function attachDeeplinks(array $results): array
    {
        $routes = config('copilot.search_routes', []);
        foreach ($results as &$result) {
            $template = $routes[$result['type']] ?? null;
            if ($template) {
                $result['deeplink'] = $this->applyTemplate($template, ['id' => $result['id'], $result['type'] . '_id' => $result['id']]);
            }
        }

        return $results;
    }

    private function fillMissingParamsFromSearch(string $input, array $requiredParams, array $params, User $user): array
    {
        $missing = array_values(array_filter($requiredParams, fn (string $param) => ! array_key_exists($param, $params)));
        if ($missing === []) {
            return $params;
        }

        $results = $this->searchService->search($input, $user, 5);
        if ($results === []) {
            return $params;
        }

        foreach ($missing as $param) {
            $match = $this->bestSearchMatchForParam($param, $results);
            if ($match) {
                $params[$param] = $match['id'];
            }
        }

        return $params;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, mixed>|null
     */
    private function bestSearchMatchForParam(string $param, array $results): ?array
    {
        $expectedType = $this->searchTypeForParam($param);
        if (! $expectedType) {
            return null;
        }

        $matches = array_values(array_filter($results, fn (array $result) => ($result['type'] ?? null) === $expectedType));
        if ($matches === []) {
            return null;
        }

        usort($matches, fn (array $a, array $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $top = $matches[0];
        $topScore = (float) ($top['score'] ?? 0.0);
        if ($topScore < (float) config('copilot.learning.search_result_min_score', 0.72)) {
            return null;
        }

        $secondScore = (float) ($matches[1]['score'] ?? 0.0);
        if (($topScore - $secondScore) < 0.05) {
            return null;
        }

        return $top;
    }

    private function searchTypeForParam(string $param): ?string
    {
        return match ($param) {
            'invoice_id' => 'invoice',
            'boat_id', 'yacht_id' => 'boat',
            'harbor_id', 'location_id' => 'harbor',
            'user_id' => 'user',
            'deal_id' => 'deal',
            'payment_id' => 'payment',
            default => null,
        };
    }

    /**
     * @param  int|array<int>|null  $locationScope
     * @return array{answers:array<int, array<string, mixed>>,trace:array<string, mixed>|null,confidence:float,strategy:string|null}
     */
    private function buildAnswers(string $input, int|array|null $locationScope = null, array $context = []): array
    {
        if (! $this->shouldBuildAnswers($input)) {
            return [
                'answers' => [],
                'trace' => null,
                'confidence' => 0.0,
                'strategy' => null,
            ];
        }

        $knowledge = $this->faqService->answer($input, $locationScope, $context, 3);
        $answers = array_map(function (array $answer) {
            $answer['actions'] = $answer['actions'] ?? $this->relatedActions(
                trim(implode(' ', array_filter([
                    $answer['question'] ?? null,
                    $answer['answer'] ?? null,
                ])))
            );

            return $answer;
        }, $knowledge['answers'] ?? []);

        // Add historical sales integration
        if ($this->isHistoricalQuery($input)) {
            try {
                $historical = $this->pineconeMatcher->matchAndBuildConsensus([], $input);
                if (!empty($historical['top_matches'])) {
                    $consensus = $historical['consensus_values'];
                    $topMatch = $historical['top_matches'][0]['boat'] ?? [];
                    
                    $answerParts = ["Based on the Schepenkring sold boats archive:"];
                    
                    if (!empty($consensus['loa'])) $answerParts[] = "• Typical length (LOA): {$consensus['loa']}m";
                    if (!empty($consensus['engine_manufacturer'])) $answerParts[] = "• Common engine: {$consensus['engine_manufacturer']}";
                    if (!empty($topMatch['price'])) $answerParts[] = "• Historical price reference: €" . number_format($topMatch['price']);
                    
                    if (count($answerParts) > 1) {
                        $answers[] = [
                            'question' => "Market Data: " . ($topMatch['manufacturer'] ?? '') . " " . ($topMatch['model'] ?? ''),
                            'answer' => implode("\n", $answerParts),
                            'category' => 'Market Intelligence',
                            'source' => 'schepenkring_archive',
                            'source_type' => 'historical_archive',
                            'strategy' => 'historical_reference',
                            'confidence' => 0.65,
                            'confidence_label' => 'medium',
                            'used_fallback' => false,
                            'sources' => [],
                            'actions' => [],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning("Historical chat lookup failed: " . $e->getMessage());
            }
        }

        return [
            'answers' => $answers,
            'trace' => $knowledge['trace'] ?? null,
            'confidence' => (float) ($knowledge['confidence'] ?? 0.0),
            'strategy' => $knowledge['strategy'] ?? null,
        ];
    }

    /**
     * @return int|array<int>|null
     */
    private function faqLocationScope(User $user, array $context): int|array|null
    {
        $contextLocation = $context['location_id'] ?? $context['harbor_id'] ?? null;
        if (is_numeric($contextLocation)) {
            return (int) $contextLocation;
        }

        if ($user->isAdmin()) {
            return null;
        }

        if ($user->isClient()) {
            return $user->client_location_id ?: null;
        }

        return $this->locationAccess->accessibleLocationIds($user);
    }

    private function relatedActions(string $text): array
    {
        $matches = [];
        $map = config('copilot.faq_action_map', []);
        $normalized = $this->matcher->normalize($text);
        foreach ($map as $keyword => $actionId) {
            if (str_contains($normalized, $this->matcher->normalize($keyword))) {
                $matches[] = ['action_id' => $actionId];
            }
        }

        return $matches;
    }

    private function looksLikeQuestion(string $input): bool
    {
        return $this->copilotLanguage()->looksLikeQuestion($input);
    }

    private function copilotLanguage(): CopilotLanguage
    {
        if (isset($this->language) && $this->language instanceof CopilotLanguage) {
            return $this->language;
        }

        return app(CopilotLanguage::class);
    }

    private function shouldBuildAnswers(string $input): bool
    {
        if (! $this->looksLikeQuestion($input)) {
            return false;
        }

        $normalized = $this->matcher->normalize($input);

        return preg_match('/\b(open|openen|ouvrir|oeffnen|search|zoeken|rechercher|find|vinden|chercher)\b/', $normalized) !== 1;
    }

    private function isHistoricalQuery(string $input): bool
    {
        return preg_match('/\b(price|sold|market|archive|historical|valuation|worth)\b/i', $input) === 1;
    }

    private function anyNeedsConfirmation(array $actions): bool
    {
        foreach ($actions as $action) {
            if (!empty($action['confirmation_required']) || ($action['risk_level'] ?? '') === 'high') {
                return true;
            }
        }

        return false;
    }

    private function extractNumericId(string $input): ?int
    {
        if (preg_match('/\\b(\\d{1,9})\\b/', $input, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function boatExistsForUser(int $id, User $user): bool
    {
        $query = Boat::query()->where('id', $id);
        if ($user->isAdmin()) {
            return $query->exists();
        }
        if ($user->isClient()) {
            return $query->where('client_id', $user->id)->exists();
        }

        $locationIds = $this->locationAccess->accessibleLocationIds($user);
        if (count($locationIds) === 0) {
            return false;
        }

        return $query->whereIn('location_id', $locationIds)->exists();
    }

    private function locationExistsForUser(int $id, User $user): bool
    {
        if ($user->isAdmin()) {
            return Location::where('id', $id)->exists();
        }

        return $this->locationAccess->sharesLocation($user, $id);
    }

    private function userExistsForUser(int $id, User $user): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return User::where('id', $id)->exists();
    }
}
