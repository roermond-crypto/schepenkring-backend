<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LoginAttemptService
{
    public function isSoftLocked(string $email, string $ip, ?string $asn = null): bool
    {
        return Cache::has($this->lockKey($email, $ip, $asn));
    }

    public function registerFailedAttempt(string $email, string $ip, ?string $asn = null): int
    {
        $window = $this->windowMinutes();
        $comboKey = $this->comboKey($email, $ip, $asn);
        $count = $this->incrementWithTtl($comboKey, $window);

        $this->incrementWithTtl($this->emailKey($email), $window);
        $this->incrementWithTtl($this->ipKey($ip), $window);
        $this->rememberUnique($this->ipUsersKey($ip), $this->hashValue($email), $window);
        $this->rememberUnique($this->userIpsKey($email), $this->hashValue($ip), $window);

        if ($count >= $this->softLockAfter()) {
            Cache::put($this->lockKey($email, $ip, $asn), true, now()->addMinutes($this->softLockMinutes()));
        }

        return $count;
    }

    public function clearForSuccess(string $email, string $ip, ?string $asn = null): void
    {
        Cache::forget($this->comboKey($email, $ip, $asn));
        Cache::forget($this->lockKey($email, $ip, $asn));
        Cache::forget($this->emailKey($email));
    }

    public function delaySeconds(string $email, string $ip, ?string $asn = null): int
    {
        $count = (int) Cache::get($this->comboKey($email, $ip, $asn), 0);
        $next = $count + 1;
        $schedule = (array) config('security.login.delay_schedule', []);

        if (isset($schedule[$next])) {
            return (int) $schedule[$next];
        }

        return 0;
    }

    public function requiresCaptcha(string $email, string $ip, ?string $asn = null): bool
    {
        $count = (int) Cache::get($this->comboKey($email, $ip, $asn), 0);
        $next = $count + 1;
        $after = (int) config('security.login.captcha_after', 7);
        $until = (int) config('security.login.captcha_until', 9);

        return $next >= $after && $next <= $until;
    }

    public function shouldRequireStepUp(string $email, string $ip): bool
    {
        $ipUsers = $this->uniqueCount($this->ipUsersKey($ip));
        $userIps = $this->uniqueCount($this->userIpsKey($email));

        $ipThreshold = (int) config('security.login.suspicious.ip_unique_users', 8);
        $userThreshold = (int) config('security.login.suspicious.user_unique_ips', 5);

        return $ipUsers >= $ipThreshold || $userIps >= $userThreshold;
    }

    public function windowMinutes(): int
    {
        return (int) config('security.login.window_minutes', 30);
    }

    public function softLockAfter(): int
    {
        return (int) config('security.login.soft_lock_after', 10);
    }

    public function softLockMinutes(): int
    {
        return (int) config('security.login.soft_lock_minutes', 15);
    }

    private function incrementWithTtl(string $key, int $ttlMinutes): int
    {
        $current = Cache::get($key);
        if ($current === null) {
            Cache::put($key, 1, now()->addMinutes($ttlMinutes));
            return 1;
        }

        $next = (int) $current + 1;
        Cache::put($key, $next, now()->addMinutes($ttlMinutes));
        return $next;
    }

    private function rememberUnique(string $key, string $value, int $ttlMinutes): void
    {
        $list = Cache::get($key, []);
        if (!is_array($list)) {
            $list = [];
        }

        if (!in_array($value, $list, true)) {
            $list[] = $value;
            Cache::put($key, $list, now()->addMinutes($ttlMinutes));
        }
    }

    private function uniqueCount(string $key): int
    {
        $list = Cache::get($key, []);
        if (!is_array($list)) {
            return 0;
        }

        return count($list);
    }

    private function comboKey(string $email, string $ip, ?string $asn): string
    {
        $suffix = $asn ? $ip . '|' . $asn : $ip;
        return 'login:combo:' . $this->hashValue($email) . ':' . $this->hashValue($suffix);
    }

    private function lockKey(string $email, string $ip, ?string $asn): string
    {
        $suffix = $asn ? $ip . '|' . $asn : $ip;
        return 'login:lock:' . $this->hashValue($email) . ':' . $this->hashValue($suffix);
    }

    private function emailKey(string $email): string
    {
        return 'login:email:' . $this->hashValue($email);
    }

    private function ipKey(string $ip): string
    {
        return 'login:ip:' . $this->hashValue($ip);
    }

    private function ipUsersKey(string $ip): string
    {
        return 'login:ip-users:' . $this->hashValue($ip);
    }

    private function userIpsKey(string $email): string
    {
        return 'login:user-ips:' . $this->hashValue($email);
    }

    private function hashValue(string $value): string
    {
        return sha1(Str::lower(trim($value)));
    }
}
