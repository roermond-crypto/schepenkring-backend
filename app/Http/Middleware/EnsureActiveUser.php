<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->isActive() && !in_array($user->status, [
            \App\Enums\UserStatus::PENDING_APPROVAL, 
            \App\Enums\UserStatus::PENDING,
            \App\Enums\UserStatus::EMAIL_PENDING,
            \App\Enums\UserStatus::VERIFYING
        ], true)) {
            $token = $user->currentAccessToken();
            if ($token) {
                $token->delete();
            }

            return response()->json([
                'message' => 'Account is not active.',
            ], 403);
        }

        return $next($request);
    }
}
