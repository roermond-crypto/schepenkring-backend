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

        $allowed = $user->isAdmin() || $user->isEmployee();

        if (!$allowed) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
