<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyInternalSecret
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('voice.internal_secret');
        if ($secret === '') {
            return $next($request);
        }

        $incoming = $request->header('X-Internal-Secret')
            ?? $request->header('X-Voice-Secret');

        if (!$incoming || !hash_equals($secret, (string) $incoming)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
