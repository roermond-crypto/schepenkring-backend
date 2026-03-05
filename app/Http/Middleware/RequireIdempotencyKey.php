<?php

namespace App\Http\Middleware;

use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;

class RequireIdempotencyKey
{
    public function __construct(private IdempotencyService $idempotency)
    {
    }

    public function handle(Request $request, Closure $next, string $mode = 'block', int $ttlSeconds = 900)
    {
        $result = $this->idempotency->begin($request, $ttlSeconds);

        if ($result['status'] === 'missing') {
            return response()->json([
                'message' => 'Idempotency-Key header or idempotency_key is required.',
            ], 400);
        }

        if ($result['status'] === 'unauthorized') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($result['status'] === 'conflict') {
            return response()->json([
                'message' => 'Idempotency-Key reuse with different request.',
            ], 409);
        }

        if ($result['status'] === 'processing') {
            return response()->json([
                'message' => 'Request already in progress.',
            ], 409);
        }

        if ($result['status'] === 'replay') {
            return $result['response'];
        }

        $response = $next($request);

        $record = $result['record'] ?? null;
        if ($record) {
            $this->idempotency->storeResponse($record, $response);
        }

        return $response;
    }
}
