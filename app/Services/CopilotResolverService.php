<?php

namespace App\Services;

use App\Models\CopilotAction;
use App\Models\CopilotActionPhrase;
use App\Models\Deal;
use App\Models\InvoiceDocument;
use App\Models\Payment;
use App\Models\User;
use App\Models\Yacht;

class CopilotResolverService
{
    public function __construct(
        private CopilotSearchService $searchService,
        private CopilotPermissionService $permissionService,
        private CopilotFuzzyMatcher $matcher,
        private CopilotAiRouterService $aiRouter,
        private CopilotFaqService $faqService,
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
            $actionCandidates = $this->resolveByPhrases($input, $language, $context);

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
                $actions = $this->buildActionsFromCandidates($actionCandidates, $input, $user, $clarifying);
                if (!empty($actions)) {
                    $confidence = (float) ($actions[0]['score'] ?? 0.4);
                }
            }
        }

        $results = $this->searchService->search($input, $user, (int) config('copilot.fuzzy_limit', 8));
        $results = $this->attachDeeplinks($results);

        $answers = $this->buildAnswers($input);

        if (empty($actions) && empty($results) && empty($answers)) {
            $clarifying = $clarifying ?: 'Can you specify what you want to open or search for?';
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

    private function resolveByPhrases(string $input, ?string $language, array $context): array
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

    private function buildActionsFromCandidates(array $candidates, string $input, User $user, ?string &$clarifying): array
    {
        $actions = [];
        foreach ($candidates as $candidate) {
            $action = $this->actionFromCatalog($candidate['action_id'], $this->extractParams($input, $candidate['required_params'] ?? []), $user);
            if (!$action) {
                continue;
            }
            $action['score'] = $candidate['score'] ?? null;
            $action['reason'] = $candidate['reason'] ?? null;
            $actions[] = $action;
        }

        if (count($actions) > 1 && !$clarifying) {
            $clarifying = 'Which action did you mean?';
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

        if (!$this->permissionService->canUseAction($user, $action->permission_key)) {
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
                case 'invoice_id':
                    if (!InvoiceDocument::where('id', $id)->exists()) {
                        return false;
                    }
                    break;
                case 'boat_id':
                case 'yacht_id':
                    if (!Yacht::where('id', $id)->exists()) {
                        return false;
                    }
                    break;
                case 'harbor_id':
                case 'user_id':
                    if (!User::where('id', $id)->exists()) {
                        return false;
                    }
                    break;
                case 'payment_id':
                    if (!Payment::where('id', $id)->exists()) {
                        return false;
                    }
                    break;
                case 'deal_id':
                    if (!Deal::where('id', $id)->exists()) {
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
            'invoice' => $this->canViewEntity($user, InvoiceDocument::where('id', $id)->exists()),
            'boat' => $this->canViewEntity($user, Yacht::where('id', $id)->exists()),
            'harbor' => $this->canViewEntity($user, User::where('id', $id)->where('role', 'Partner')->exists()),
            'user' => $this->canViewEntity($user, User::where('id', $id)->exists()),
            'payment' => $this->canViewEntity($user, Payment::where('id', $id)->exists()),
            'deal' => $this->canViewEntity($user, Deal::where('id', $id)->exists()),
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

    private function buildAnswers(string $input): array
    {
        if (!$this->looksLikeQuestion($input)) {
            return [];
        }

        $answers = [];
        $faqItems = $this->faqService->search($input, 2);

        foreach ($faqItems as $faq) {
            $answers[] = [
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'category' => $faq['category'],
                'actions' => $this->relatedActions($faq['question'] . ' ' . $faq['answer']),
            ];
        }

        return $answers;
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
        $trimmed = strtolower(trim($input));
        return str_ends_with($trimmed, '?') || str_starts_with($trimmed, 'how') || str_starts_with($trimmed, 'hoe') || str_starts_with($trimmed, 'wie');
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

    private function canViewEntity(User $user, bool $exists): bool
    {
        if (!$exists) {
            return false;
        }

        $role = strtolower((string) $user->role);
        if ($role === 'admin' || $role === 'superadmin') {
            return true;
        }

        return true;
    }
}
