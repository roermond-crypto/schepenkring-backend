<?php

namespace App\Services;

use App\Mail\UserVerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailVerificationCodeService
{
    private const TTL_MINUTES = 15;

    public function issue(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user->email), [
            'hash' => hash('sha256', $code),
        ], now()->addMinutes(self::TTL_MINUTES));

        try {
            Mail::to($user->email)->send(new UserVerificationCodeMail($user, $code, self::TTL_MINUTES));
        } catch (\Throwable $exception) {
            Log::error('User verification code email failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function verify(User $user, string $code): bool
    {
        $payload = Cache::get($this->cacheKey($user->email));
        if (! is_array($payload) || ! isset($payload['hash']) || ! is_string($payload['hash'])) {
            return false;
        }

        $isValid = hash_equals($payload['hash'], hash('sha256', trim($code)));

        if ($isValid) {
            Cache::forget($this->cacheKey($user->email));
        }

        return $isValid;
    }

    public function ttlMinutes(): int
    {
        return self::TTL_MINUTES;
    }

    private function cacheKey(string $email): string
    {
        return 'auth:email-verification-code:' . sha1(strtolower(trim($email)));
    }
}
