<?php

namespace App\Services;

use App\Enums\RiskLevel;
use App\Models\AuditLog;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ActionSecurity
{
    public function __construct(private ImpersonationContext $impersonationContext)
    {
    }

    public function requireIdempotency(?string $key, string $action, User $actor): void
    {
        if (! $key) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency-Key header is required for this action.',
            ]);
        }

        try {
            IdempotencyKey::create([
                'key' => Str::limit($key, 255, ''),
                'action' => $action,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            throw new ConflictHttpException('Duplicate request detected.');
        }
    }

    public function assertFreshAuth(User $user, string $password, ?string $otpCode = null): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Invalid password.',
            ]);
        }

        if ($user->two_factor_enabled) {
            if (! $otpCode) {
                throw ValidationException::withMessages([
                    'otp_code' => 'OTP code is required for this action.',
                ]);
            }

            if (! $user->otp_secret || ! Totp::verify($user->otp_secret, $otpCode)) {
                throw ValidationException::withMessages([
                    'otp_code' => 'Invalid OTP code.',
                ]);
            }
        }
    }

    public function log(string $action, RiskLevel $risk, User $actor, ?object $target = null, array $meta = []): AuditLog
    {
        $impersonator = $this->impersonationContext->impersonator();

        return AuditLog::create([
            'action' => $action,
            'risk_level' => $risk->value,
            'actor_id' => $actor->id,
            'impersonator_id' => $impersonator?->id,
            'target_type' => $target ? $target::class : null,
            'target_id' => $target->id ?? null,
            'meta' => $meta,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
