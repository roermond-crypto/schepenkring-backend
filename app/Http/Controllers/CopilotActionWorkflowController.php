<?php

namespace App\Http\Controllers;

use App\Models\CopilotAction;
use App\Models\CopilotAuditEvent;
use App\Services\CopilotActionExecutionService;
use App\Services\CopilotActionMatcherService;
use App\Services\CopilotActionTokenService;
use App\Services\CopilotActionValidationService;
use App\Services\CopilotAiRouterService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CopilotActionWorkflowController extends Controller
{
    public function __construct(
        private CopilotActionMatcherService $matcher,
        private CopilotAiRouterService $aiRouter,
        private CopilotActionValidationService $validator,
        private CopilotActionTokenService $tokenService,
        private CopilotActionExecutionService $executor
    ) {
    }

    public function draft(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'language' => 'nullable|string|max:10',
            'module' => 'nullable|string|max:80',
            'top_k' => 'nullable|integer|min:1|max:10',
            'context' => 'nullable|array',
        ]);

        $start = microtime(true);
        $user = $request->user();
        $prompt = trim($validated['prompt']);
        $topK = (int) ($validated['top_k'] ?? 5);
        $language = $validated['language'] ?? null;
        $module = $validated['module'] ?? null;
        $context = $validated['context'] ?? [];

        $candidates = $this->matcher->match($prompt, $user, $language, $module, $topK);
        if (empty($candidates)) {
            $fallback = CopilotAction::query()
                ->where('enabled', true)
                ->orderBy('module')
                ->orderBy('title')
                ->limit($topK)
                ->get();

            $candidates = $fallback->map(function (CopilotAction $action) {
                return [
                    'action_id' => $action->action_id,
                    'title' => $action->title,
                    'required_params' => $action->required_params ?? [],
                    'score' => 0.1,
                    'reason' => 'Fallback list',
                ];
            })->all();
        }

        $aiResult = null;
        $selected = null;
        if (!empty($candidates)) {
            $aiResult = $this->aiRouter->route($prompt, $candidates, $context);
            if ($aiResult && !empty($aiResult['action_id'])) {
                $selected = $this->buildSelection($aiResult['action_id'], $aiResult['params'] ?? [], $user);
            }
        }

        $draftId = (string) Str::uuid();
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        CopilotAuditEvent::create([
            'user_id' => $user->id,
            'source' => 'admin_copilot',
            'stage' => 'draft',
            'input_text' => $prompt,
            'resolved_action_candidates' => $candidates,
            'selected_action_id' => $selected['action_id'] ?? null,
            'selected_action_params' => $selected['params'] ?? null,
            'confidence' => $aiResult['confidence'] ?? null,
            'status' => $selected ? 'drafted' : 'no_match',
            'failure_reason' => $selected ? null : 'No action selected',
            'matching_detail' => [
                'ai_result' => $aiResult,
            ],
            'duration_ms' => $durationMs,
            'request_id' => $request->attributes->get('request_id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json([
            'draft_id' => $draftId,
            'prompt' => $prompt,
            'selected_action' => $selected,
            'candidates' => $candidates,
            'confidence' => $aiResult['confidence'] ?? null,
            'clarifying_question' => $aiResult['clarifying_question'] ?? null,
        ]);
    }

    public function validateAction(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'action_id' => 'required|string|max:120',
            'payload' => 'nullable|array',
        ]);

        $user = $request->user();
        $payload = $validated['payload'] ?? [];
        $action = CopilotAction::query()->where('action_id', $validated['action_id'])->where('enabled', true)->first();

        if (!$action) {
            return response()->json(['message' => 'Action not found'], 404);
        }

        $start = microtime(true);
        $result = $this->validator->validateAction($user, $action, $payload);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $requiresConfirmation = (bool) $action->confirmation_required || $action->risk_level === 'high';

        CopilotAuditEvent::create([
            'user_id' => $user->id,
            'source' => 'admin_copilot',
            'stage' => 'validate',
            'input_text' => null,
            'resolved_action_candidates' => null,
            'selected_action_id' => $action->action_id,
            'selected_action_params' => $payload,
            'confidence' => null,
            'status' => $result['ok'] ? 'validated' : 'failed',
            'failure_reason' => $result['ok'] ? null : 'Validation failed',
            'validation_result' => $result,
            'duration_ms' => $durationMs,
            'request_id' => $request->attributes->get('request_id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        if (!$result['ok']) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $result['errors'],
            ], 422);
        }

        $token = $this->tokenService->issue($user, $action, $payload);

        return response()->json([
            'validation_token' => $token,
            'action_id' => $action->action_id,
            'requires_confirmation' => $requiresConfirmation,
            'payload' => $payload,
        ]);
    }

    public function execute(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'validation_token' => 'required|string',
            'confirm' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $data = $this->tokenService->decode($validated['validation_token']);
        if (!$data) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        if (($data['user_id'] ?? null) !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $action = CopilotAction::query()->where('action_id', $data['action_id'] ?? null)->where('enabled', true)->first();
        if (!$action) {
            return response()->json(['message' => 'Action not found'], 404);
        }

        $requiresConfirmation = (bool) $action->confirmation_required || $action->risk_level === 'high';
        if ($requiresConfirmation && !$request->boolean('confirm')) {
            return response()->json([
                'message' => 'Confirmation required',
                'requires_confirmation' => true,
            ], 409);
        }

        $start = microtime(true);
        $execution = $this->executor->execute($action, $data['payload'] ?? []);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        CopilotAuditEvent::create([
            'user_id' => $user->id,
            'source' => 'admin_copilot',
            'stage' => 'execute',
            'input_text' => null,
            'resolved_action_candidates' => null,
            'selected_action_id' => $action->action_id,
            'selected_action_params' => $data['payload'] ?? [],
            'confidence' => null,
            'status' => 'executed',
            'execution_result' => $execution,
            'duration_ms' => $durationMs,
            'request_id' => $request->attributes->get('request_id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'executed',
            'action_id' => $action->action_id,
            'payload' => $data['payload'] ?? [],
            'execution' => $execution,
        ]);
    }

    private function buildSelection(string $actionId, array $params, $user): ?array
    {
        $action = CopilotAction::query()->where('action_id', $actionId)->where('enabled', true)->first();
        if (!$action) {
            return null;
        }

        return [
            'action_id' => $action->action_id,
            'title' => $action->title,
            'params' => $params,
            'risk_level' => $action->risk_level,
            'confirmation_required' => (bool) $action->confirmation_required,
            'input_schema' => $action->input_schema,
            'example_inputs' => $action->example_inputs ?? [],
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthorized');
        }
        $role = strtolower((string) $user->role);
        if ($role !== 'admin' && $role !== 'superadmin') {
            abort(403, 'Forbidden');
        }
    }
}
