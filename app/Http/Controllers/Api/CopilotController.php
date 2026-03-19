<?php

namespace App\Http\Controllers\Api;

use App\Models\CopilotAuditEvent;
use App\Http\Controllers\Controller;
use App\Services\CopilotFeedbackService;
use App\Services\KnowledgeBrainService;
use App\Services\CopilotLearningService;
use App\Services\CopilotResolverService;
use App\Support\CopilotLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class CopilotController extends Controller
{
    public function __construct(
        private CopilotResolverService $resolver,
        private CopilotLanguage $language,
        private CopilotLearningService $learning,
        private KnowledgeBrainService $brain
    )
    {
    }

    public function resolve(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'text' => 'required|string|max:500',
            'source' => 'nullable|string|in:header,chatpage,voice',
            'context' => 'nullable|array',
        ]);

        $rateKey = 'copilot:' . $user->id . ':' . $request->ip();
        $maxAttempts = (int) config('copilot.rate_limit.max_attempts', 30);
        $decay = (int) config('copilot.rate_limit.decay_seconds', 60);
        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            return response()->json(['message' => 'Too many requests'], 429);
        }
        RateLimiter::hit($rateKey, $decay);

        $context = $validated['context'] ?? [];
        $context['source'] = $validated['source'] ?? 'header';
        $resolvedLanguage = $this->language->resolve(
            $validated['text'],
            $context['language'] ?? null,
            $request->header('Accept-Language'),
            $user->locale
        );
        $context['language'] = $resolvedLanguage['language'];

        $response = $this->resolver->resolve($validated['text'], $user, $context);
        $localeUpdated = $this->updateUserLocale($user, $resolvedLanguage['language']);
        $response['language'] = $resolvedLanguage['language'];
        $response['header_language'] = $resolvedLanguage['header_language'];
        $response['language_detected_from_input'] = $resolvedLanguage['detected_from_input'];
        $response['locale_updated'] = $localeUpdated;

        if (! $this->isPreviewMode($context)) {
            $this->logResolveEvent($request, $user->id, $validated['text'], $response);
            $this->brain->captureCopilotResolution($user, $validated['text'], $response, $context);
        }

        return response()
            ->json($response)
            ->header('Content-Language', $resolvedLanguage['language'])
            ->header('X-Header-Language', $resolvedLanguage['header_language']);
    }

    public function track(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'source' => 'nullable|string|in:header,chatpage,voice',
            'event' => 'required|string|max:60',
            'input_text' => 'nullable|string|max:500',
            'action_id' => 'nullable|string|max:120',
            'action_params' => 'nullable|array',
            'deeplink' => 'nullable|string|max:2048',
            'confidence' => 'nullable|numeric',
            'status' => 'nullable|string|max:30',
            'failure_reason' => 'nullable|string|max:200',
            'request_id' => 'nullable|string|max:80',
        ]);

        $event = CopilotAuditEvent::create([
            'user_id' => $user->id,
            'source' => $validated['source'] ?? 'header',
            'input_text' => $validated['input_text'] ?? null,
            'selected_action_id' => $validated['action_id'] ?? null,
            'selected_action_params' => $validated['action_params'] ?? null,
            'deeplink_returned' => $validated['deeplink'] ?? null,
            'confidence' => $validated['confidence'] ?? null,
            'status' => $validated['status'] ?? $validated['event'],
            'failure_reason' => $validated['failure_reason'] ?? null,
            'request_id' => $validated['request_id'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        $this->learning->ingestCopilotAuditEvent($event);

        return response()->json(['message' => 'tracked']);
    }

    public function feedback(Request $request, CopilotFeedbackService $feedback)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (! $user->isStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'audit_event_id' => 'nullable|integer|exists:copilot_audit_events,id',
            'faq_id' => 'nullable|integer|exists:faqs,id',
            'supersede_faq_id' => 'nullable|integer|exists:faqs,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'question' => 'nullable|string|max:255',
            'wrong_answer' => 'nullable|string',
            'corrected_answer' => 'required|string',
            'category' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:5',
            'department' => 'nullable|string|max:100',
            'visibility' => 'nullable|string|in:internal,staff,public',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'source_type' => 'nullable|string|max:40',
        ]);

        return response()->json($feedback->capture($user, $validated), 201);
    }

    private function logResolveEvent(Request $request, int $userId, string $input, array $response): void
    {
        $actions = $response['actions'] ?? [];
        $results = $response['results'] ?? [];
        $answers = $response['answers'] ?? [];
        $candidatePayload = array_map(function ($action) {
            return [
                'action_id' => $action['action_id'] ?? null,
                'params' => $action['params'] ?? null,
                'reason' => $action['reason'] ?? null,
                'score' => $action['score'] ?? null,
            ];
        }, $actions);

        $event = CopilotAuditEvent::create([
            'user_id' => $userId,
            'source' => $response['source'] ?? 'header',
            'input_text' => $input,
            'resolved_action_candidates' => $candidatePayload,
            'matching_detail' => [
                'search_results' => $results,
                'answers' => $answers,
                'knowledge_trace' => $response['knowledge_trace'] ?? null,
                'answer_strategy' => $response['answer_strategy'] ?? null,
                'clarifying_question' => $response['clarifying_question'] ?? null,
            ],
            'deeplink_returned' => $actions[0]['deeplink'] ?? null,
            'confidence' => $response['confidence'] ?? null,
            'status' => empty($actions) && empty($answers) ? 'no_match' : 'resolved',
            'failure_reason' => empty($actions) && empty($answers) ? 'no_action' : null,
            'request_id' => $request->header('X-Request-Id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        $this->learning->ingestCopilotAuditEvent($event);
    }

    private function updateUserLocale($user, string $language): bool
    {
        if (($user->locale ?? null) === $language) {
            return false;
        }

        $user->forceFill([
            'locale' => $language,
        ])->saveQuietly();

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isPreviewMode(array $context): bool
    {
        return filter_var($context['preview_mode'] ?? false, FILTER_VALIDATE_BOOL);
    }
}
