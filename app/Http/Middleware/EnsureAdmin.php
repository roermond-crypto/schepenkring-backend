<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $role = strtolower((string) $user->role);
        $allowed = $role === 'admin';

        if (!$allowed && method_exists($user, 'hasRole')) {
            $allowed = $user->hasRole('Admin');
        }

        if (!$allowed) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
