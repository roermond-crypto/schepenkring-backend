<?php

namespace App\Services;

use App\Models\BlockedContact;
use App\Models\BlockedIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ChatAbuseService
{
    public function ensureNotBlocked(?string $email, ?string $phone, ?string $whatsappId, ?string $ip): void
    {
        if ($ip && $this->isIpBlocked($ip)) {
            abort(403, 'IP blocked.');
        }

        if ($email && $this->isContactBlocked('email', $email)) {
            abort(403, 'Contact blocked.');
        }

        if ($phone && $this->isContactBlocked('phone', $phone)) {
            abort(403, 'Contact blocked.');
        }

        if ($whatsappId && $this->isContactBlocked('whatsapp', $whatsappId)) {
            abort(403, 'Contact blocked.');
        }
    }

    public function rateLimit(Request $request, ?string $visitorId, ?string $contactKey): void
    {
        $maxAttempts = (int) env('CHAT_RATE_LIMIT_PER_MINUTE', 30);
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

    private function isIpBlocked(string $ip): bool
    {
        return BlockedIp::where('ip', $ip)
            ->where(function ($query) {
                $query->whereNull('blocked_until')->orWhere('blocked_until', '>', now());
            })
            ->exists();
    }

    private function isContactBlocked(string $type, string $value): bool
    {
        return BlockedContact::where('type', $type)
            ->where('value', $value)
            ->where(function ($query) {
                $query->whereNull('blocked_until')->orWhere('blocked_until', '>', now());
            })
            ->exists();
    }
}
