<?php

namespace App\Http\Controllers\Api\RetellTool;

use App\Models\AuditLog;
use App\Models\CallSession;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    /**
     * Every tool call — successful or not — gets an AuditLog row (spec §19:
     * "Tool call executed" / "Tool call failed", with actor/endpoint/
     * status/request_id). There's no acting user to log as actor, and
     * AuditLog.target_id is a legacy unsignedBigInteger column shared with
     * many other features — CallSession uses UUID primary keys, so the
     * call is identified via meta.call_session_id instead of target_id
     * rather than risking an insert failure on a column this feature
     * doesn't own.
     */
    protected function safe(\Closure $callback): JsonResponse
    {
        $requestId = (string) Str::uuid();
        $endpoint = request()->path();
        $session = $this->resolveCallSession(request());

        try {
            $response = $callback();

            AuditLog::create([
                'action' => 'retell_tool.executed',
                'category' => 'voice_ai',
                'result' => 'success',
                'location_id' => $session?->harbor_id,
                'meta' => ['endpoint' => $endpoint, 'status' => $response->getStatusCode(), 'call_session_id' => $session?->id],
                'request_id' => $requestId,
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('Retell tool call failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            AuditLog::create([
                'action' => 'retell_tool.failed',
                'category' => 'voice_ai',
                'risk_level' => 'medium',
                'result' => 'failure',
                'location_id' => $session?->harbor_id,
                'meta' => ['endpoint' => $endpoint, 'error' => $e->getMessage(), 'call_session_id' => $session?->id],
                'request_id' => $requestId,
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
