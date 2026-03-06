<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to restrict access by user role(s).
 *
 * Usage in routes:
 *   Route::middleware('role:admin')->group(...)
 *   Route::middleware('role:admin,employee')->group(...)
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'You do not have permission to access this resource.',
                'required_roles' => $roles,
                'your_role' => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
