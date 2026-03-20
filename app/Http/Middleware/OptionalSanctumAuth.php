<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attempt Sanctum token authentication without enforcing it.
 *
 * This middleware is used on routes that are accessible to both guests and
 * authenticated users. If a valid Bearer token is present, the user will be
 * resolved and available via $request->user(). If no token is present (or the
 * token is invalid), the request continues as a guest (user = null).
 *
 * This fixes the issue where logged-in dashboard users sending chat messages
 * were treated as "Anonymous" because the chat routes were in the public
 * (unauthenticated) route group, so Sanctum never attempted to resolve the user.
 */
class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only attempt authentication if a Bearer token is actually present.
        // This avoids unnecessary guard resolution overhead for pure guest requests.
        if ($request->bearerToken()) {
            try {
                /** @var \Illuminate\Auth\RequestGuard $guard */
                $guard = Auth::guard('sanctum');
                $user = $guard->user();

                if ($user) {
                    // Bind the resolved user back onto the request so that
                    // $request->user() returns the authenticated user downstream.
                    Auth::setUser($user);
                }
            } catch (\Throwable) {
                // Token is invalid or expired — continue as guest.
            }
        }

        return $next($request);
    }
}
