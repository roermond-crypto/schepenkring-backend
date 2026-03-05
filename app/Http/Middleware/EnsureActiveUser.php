<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $status = strtolower((string) $user->status);
        if ($status === 'active') {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';
        if (str_starts_with($routeName, 'onboarding.')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Onboarding incomplete.',
            'status' => $user->status,
        ], 403);
    }
}
