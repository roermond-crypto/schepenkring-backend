<?php

namespace App\Services;

use App\Models\BlockedContact;
use Illuminate\Support\Facades\RateLimiter;

class PhoneAbuseService
{
    public function isBlocked(string $phone): bool
    {
        return BlockedContact::where('type', 'phone')
            ->where('value', $phone)
            ->where(function ($query) {
                $query->whereNull('blocked_until')->orWhere('blocked_until', '>', now());
            })
            ->exists();
    }

    public function registerCallAttempt(string $phone): bool
    {
        if ($this->isBlocked($phone)) {
            return false;
        }

        $hourLimit = (int) config('voice.call_limit_per_hour', 5);
        $dayLimit = (int) config('voice.call_limit_per_day', 20);
        $blockMinutes = (int) config('voice.block_duration_minutes', 1440);

        $hourKey = "phone:{$phone}:hour";
        $dayKey = "phone:{$phone}:day";

        if (RateLimiter::tooManyAttempts($hourKey, $hourLimit)) {
            return false;
        }

        RateLimiter::hit($hourKey, 3600);
        RateLimiter::hit($dayKey, 86400);

        if (RateLimiter::tooManyAttempts($dayKey, $dayLimit)) {
            BlockedContact::updateOrCreate([
                'type' => 'phone',
                'value' => $phone,
            ], [
                'reason' => 'high_frequency',
                'blocked_until' => now()->addMinutes($blockMinutes),
            ]);
            return false;
        }

        return true;
    }
}
