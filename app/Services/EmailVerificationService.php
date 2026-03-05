<?php

namespace App\Services;

use App\Mail\EmailVerificationCodeMail;
use App\Models\EmailVerificationToken;
use App\Models\EmailVerificationAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailVerificationService
{
    public function sendCode(User $user, Request $request, array $context = []): array
    {
        $ttl = $this->ttlMinutes();
        $code = (string) random_int(100000, 999999);
        $token = $this->issueToken($user, $code, $ttl);

        $overLimit = $this->sendRateLimited($user);
        $this->logAttempt($user, 'send', $overLimit ? 'sent_over_limit' : 'sent', $request, array_merge($context, [
            'over_limit' => $overLimit,
        ]));

        $verifyUrl = $this->buildVerificationUrl($token);
        Mail::to($user->email)->send(new EmailVerificationCodeMail($code, $ttl, $verifyUrl));

        return [
            'ttl_minutes' => $ttl,
            'over_limit' => $overLimit,
            'token' => $token,
            'verification_url' => $verifyUrl,
        ];
    }

    public function recordVerifyAttempt(User $user, string $status, Request $request, array $metadata = []): void
    {
        $overLimit = $this->verifyRateLimited($user);
        $this->logAttempt($user, 'verify', $status, $request, array_merge($metadata, [
            'over_limit' => $overLimit,
        ]));
    }

    public function findToken(string $rawToken): ?EmailVerificationToken
    {
        $hash = $this->hashToken($rawToken);
        return EmailVerificationToken::where('token_hash', $hash)->first();
    }

    public function resendCode(User $user, Request $request, array $context = []): array
    {
        return $this->sendCode($user, $request, array_merge($context, ['resend' => true]));
    }

    public function verifyToken(EmailVerificationToken $token, string $code): array
    {
        if ($token->used_at || $token->expires_at->isPast()) {
            return ['ok' => false, 'reason' => 'expired'];
        }

        if ($token->locked_until && $token->locked_until->isFuture()) {
            return ['ok' => false, 'reason' => 'locked'];
        }

        $maxAttempts = $this->maxAttempts();
        $token->attempts = $token->attempts + 1;
        if ($token->attempts >= $maxAttempts) {
            $token->locked_until = now()->addMinutes($this->lockMinutes());
        }
        $token->save();

        if (!Hash::check($code, $token->code_hash)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $token->used_at = now();
        $token->save();

        return ['ok' => true];
    }

    public function sendRateLimited(User $user): bool
    {
        $max = $this->maxSendPerWindow();
        if ($max <= 0) {
            return false;
        }

        return $this->countAttempts($user, 'send', $this->sendWindowMinutes()) >= $max;
    }

    public function verifyRateLimited(User $user): bool
    {
        $max = $this->maxVerifyPerWindow();
        if ($max <= 0) {
            return false;
        }

        return $this->countAttempts($user, 'verify', $this->verifyWindowMinutes()) >= $max;
    }

    private function logAttempt(User $user, string $action, string $status, Request $request, array $metadata = []): void
    {
        EmailVerificationAttempt::create([
            'user_id' => $user->id,
            'action' => $action,
            'status' => $status,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }

    private function countAttempts(User $user, string $action, int $windowMinutes): int
    {
        return EmailVerificationAttempt::where('user_id', $user->id)
            ->where('action', $action)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();
    }

    private function ttlMinutes(): int
    {
        return (int) config('security.email_verification.ttl_minutes', 15);
    }

    private function maxSendPerWindow(): int
    {
        return (int) config('security.email_verification.max_send_per_window', 5);
    }

    private function sendWindowMinutes(): int
    {
        return (int) config('security.email_verification.send_window_minutes', 10);
    }

    private function maxVerifyPerWindow(): int
    {
        return (int) config('security.email_verification.max_verify_per_window', 5);
    }

    private function verifyWindowMinutes(): int
    {
        return (int) config('security.email_verification.verify_window_minutes', 10);
    }

    private function buildVerificationUrl(string $token): string
    {
        $base = rtrim((string) config('app.frontend_url', env('APP_FRONTEND_URL', config('app.url'))), '/');
        $path = '/' . ltrim((string) config('app.email_verification_path', '/verify-email'), '/');
        return $base . $path . '/' . $token;
    }

    private function issueToken(User $user, string $code, int $ttl): string
    {
        EmailVerificationToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(48);
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hashToken($token),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
            'attempts' => 0,
        ]);

        return $token;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function maxAttempts(): int
    {
        return (int) config('security.email_verification.max_attempts', 5);
    }

    private function lockMinutes(): int
    {
        return (int) config('security.email_verification.lock_minutes', 15);
    }

}
