<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireSecurityLevel
{
    public function handle(Request $request, Closure $next, string $level = 'low')
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
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $config = config('security.levels.' . $level);
        if (!$config) {
            return response()->json([
                'message' => 'Security policy not configured.',
                'security_level' => $level,
            ], 500);
        }

        $requiredStrength = (string) ($config['required_strength'] ?? 'password');
        $freshMinutes = $config['fresh_minutes'] ?? null;

        $currentStrength = (string) ($token->auth_strength ?? 'password');
        if (!$this->meetsRequirement($currentStrength, $requiredStrength)) {
            return response()->json([
                'message' => 'Step-up verification required.',
                'step_up_required' => true,
                'required_level' => $level,
            ], 403);
        }

        if ($freshMinutes !== null) {
            $lastVerified = $token->last_verified_at ?? $token->created_at;
            if (!$lastVerified || $lastVerified->diffInMinutes(now()) > (int) $freshMinutes) {
                return response()->json([
                    'message' => 'Recent verification required.',
                    'step_up_required' => true,
                    'required_level' => $level,
                    'fresh_minutes' => (int) $freshMinutes,
                ], 403);
            }
        }

        return $next($request);
    }

    private function meetsRequirement(string $current, string $required): bool
    {
        $order = [
            'password' => 1,
            'otp' => 2,
            'mfa' => 3,
            'passkey' => 4,
        ];

        $currentRank = $order[$current] ?? 0;
        $requiredRank = $order[$required] ?? 1;

        return $currentRank >= $requiredRank;
    }
}
