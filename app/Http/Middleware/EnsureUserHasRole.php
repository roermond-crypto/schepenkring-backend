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

        $normalizedRoles = array_map('strtolower', $roles);
        $userRole = strtolower((string) $user->role);

        if (! in_array($userRole, $normalizedRoles, true)) {
            return response()->json([
                'message' => 'You do not have permission to access this resource.',
                'required_roles' => $normalizedRoles,
                'your_role' => $userRole,
            ], 403);
        }

        return $next($request);
    }
}
