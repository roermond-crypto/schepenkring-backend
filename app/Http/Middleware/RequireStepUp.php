<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireStepUp
{
    public function handle(Request $request, Closure $next, string $level = 'otp')
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if the user is an admin and allow them to bypass step-up
        if ($user->isAdmin()) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        $strength = $token?->auth_strength ?? 'password';

        if (!$this->meetsRequirement($strength, $level)) {
            return response()->json([
                'message' => 'Step-up verification required.',
                'step_up_required' => true,
                'required_level' => $level,
            ], 403);
        }

        return $next($request);
    }

    private function meetsRequirement(string $strength, string $required): bool
    {
        $order = [
            'password' => 1,
            'otp' => 2,
            'mfa' => 3,
            'passkey' => 4,
        ];

        $current = $order[$strength] ?? 0;
        $needed = $order[$required] ?? 2;

        return $current >= $needed;
    }
}
