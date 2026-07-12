<?php

namespace App\Http\Controllers\Api\RetellTool;

use App\Models\CallSession;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Shared plumbing for every POST /api/integrations/retell/tools/* endpoint:
 * resolve the in-progress call for location scoping, never leak internal
 * exceptions back to the LLM, and (for mutating endpoints) reuse the same
 * IdempotencyService the rest of the app already relies on rather than
 * inventing a 4th idempotency pattern.
 */
trait RetellToolHelpers
{
    protected function resolveCallSession(Request $request): ?CallSession
    {
        $callId = $request->input('call_id');

        return $callId ? CallSession::where('external_call_id', $callId)->first() : null;
    }

    /**
     * Retell has no logged-in user to scope permissions to — the call
     * itself is the tenant boundary. An entity belonging to a different
     * location than the in-progress call is refused. When the call has no
     * resolved location yet (e.g. inbound, still routing) or the entity
     * has no location, scoping is skipped rather than blocking everything.
     */
    protected function assertLocationScope(?int $entityLocationId, ?CallSession $session): void
    {
        if ($session?->harbor_id && $entityLocationId && (int) $session->harbor_id !== (int) $entityLocationId) {
            abort(403, 'This call is not authorized for that location.');
        }
    }

    protected function safe(\Closure $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::error('Retell tool call failed', [
                'endpoint' => request()->path(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }

    /**
     * For mutating endpoints only (create appointment/callback, send
     * onboarding link) — Retell may retry a tool call it didn't get a
     * response for, and a retried "create appointment" must not create a
     * second one.
     */
    protected function withIdempotency(Request $request, IdempotencyService $idempotency, \Closure $handler): JsonResponse
    {
        $result = $idempotency->begin($request);

        if ($result['status'] === 'conflict') {
            return response()->json(['error' => 'idempotency_conflict'], 409);
        }
        if ($result['status'] === 'processing') {
            return response()->json(['error' => 'request_in_progress'], 409);
        }
        if ($result['status'] === 'replay') {
            return $result['response'];
        }

        $response = $this->safe($handler);

        if (isset($result['record'])) {
            $idempotency->storeResponse($result['record'], $response);
        }

        return $response;
    }
}
