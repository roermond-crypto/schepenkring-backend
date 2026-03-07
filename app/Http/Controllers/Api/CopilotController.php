<?php

namespace App\Http\Controllers\Api;

use App\Models\CopilotAuditEvent;
use App\Http\Controllers\Controller;
use App\Services\CopilotResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class CopilotController extends Controller
{
    public function __construct(private CopilotResolverService $resolver)
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
        $context['language'] = $context['language'] ?? $request->header('Accept-Language');

        $response = $this->resolver->resolve($validated['text'], $user, $context);

        $this->logResolveEvent($request, $user->id, $validated['text'], $response);

        return response()->json($response);
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

        CopilotAuditEvent::create([
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

        return response()->json(['message' => 'tracked']);
    }

    private function logResolveEvent(Request $request, int $userId, string $input, array $response): void
    {
        $actions = $response['actions'] ?? [];
        $candidatePayload = array_map(function ($action) {
            return [
                'action_id' => $action['action_id'] ?? null,
                'params' => $action['params'] ?? null,
                'reason' => $action['reason'] ?? null,
                'score' => $action['score'] ?? null,
            ];
        }, $actions);

        CopilotAuditEvent::create([
            'user_id' => $userId,
            'source' => $response['source'] ?? 'header',
            'input_text' => $input,
            'resolved_action_candidates' => $candidatePayload,
            'deeplink_returned' => $actions[0]['deeplink'] ?? null,
            'confidence' => $response['confidence'] ?? null,
            'status' => empty($actions) ? 'no_match' : 'resolved',
            'failure_reason' => empty($actions) ? 'no_action' : null,
            'request_id' => $request->header('X-Request-Id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
