<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ChatAbuseService
{
    public function ensureNotBlocked(?string $email, ?string $phone, ?string $whatsappId, ?string $ip): void
    {
        // Phase 1: no blocklists yet.
    }

    public function rateLimit(Request $request, ?string $visitorId, ?string $contactKey): void
    {
        $maxAttempts = (int) env('CHAT_RATE_LIMIT_PER_MINUTE', 60);
        $decaySeconds = 60;

        $ip = $request->ip();
        $keys = [
            $ip ? "chat:ip:{$ip}" : null,
            $visitorId ? "chat:visitor:{$visitorId}" : null,
            $contactKey ? "chat:contact:{$contactKey}" : null,
        ];

        foreach (array_filter($keys) as $key) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                abort(429, 'Rate limit exceeded.');
            }
        }

        foreach (array_filter($keys) as $key) {
            RateLimiter::hit($key, $decaySeconds);
        }
    }
}
