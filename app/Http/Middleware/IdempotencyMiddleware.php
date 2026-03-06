<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * IdempotencyMiddleware — HTTP-level idempotency for offline sync.
 *
 * Reads the `Idempotency-Key` header on mutating requests (POST, PUT, PATCH, DELETE).
 * If the key has been seen before for this user + path, replays the stored response.
 * Otherwise, processes the request and stores the response.
 *
 * This is designed specifically for the offline outbox sync strategy where the
 * client may retry the same request multiple times.
 */
class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to mutating requests
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // If no key provided, skip idempotency logic
        if (! $idempotencyKey) {
            return $next($request);
        }

        $user = $request->user();
        $actorId = $user?->id;
        $action = $request->method() . ':' . $request->path();

        // Look up existing entry
        $existing = IdempotencyKey::where('key', $idempotencyKey)
            ->where('action', $action)
            ->where('actor_id', $actorId)
            ->first();

        if ($existing) {
            // Key already exists — replay the stored response if we have one
            if ($existing->response_code && $existing->response_body !== null) {
                return response($existing->response_body, $existing->response_code)
                    ->header('Content-Type', 'application/json')
                    ->header('X-Idempotent-Replayed', 'true');
            }

            // If the entry exists but has no stored response (old-style entry),
            // return a conflict to be safe
            return response()->json([
                'message' => 'Duplicate request detected.',
                'idempotent' => true,
            ], 409);
        }

        // Process the request
        $response = $next($request);

        // Store the response for future replays
        try {
            IdempotencyKey::create([
                'key' => substr($idempotencyKey, 0, 255),
                'action' => $action,
                'actor_id' => $actorId,
                'response_code' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'created_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Race condition — another request already inserted the key.
            // This is fine; the first response wins.
        }

        return $response;
    }
}
