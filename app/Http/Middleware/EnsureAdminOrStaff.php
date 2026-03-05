<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminOrStaff
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $role = strtolower((string) $user->role);
        $allowed = in_array($role, ['admin', 'employee'], true);

        if (!$allowed && method_exists($user, 'hasRole')) {
            $allowed = $user->hasRole('Admin') || $user->hasRole('Employee');
        }

        if (!$allowed && method_exists($user, 'can')) {
            $allowed = $user->can('manage errors');
        }

        if (!$allowed) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
