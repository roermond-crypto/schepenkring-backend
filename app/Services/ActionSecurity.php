<?php

namespace App\Services;

use App\Enums\AuditResult;
use App\Enums\RiskLevel;
use App\Jobs\IngestAuditLogLearningJob;
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
    public function __construct(
        private ImpersonationContext $impersonationContext
    )
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

    public function log(
        string $action,
        RiskLevel $risk,
        ?User $actor,
        ?object $target = null,
        array $meta = [],
        array $context = []
    ): AuditLog
    {
        $impersonator = $this->impersonationContext->impersonator();
        $request = request();

        $entityType = $context['entity_type'] ?? ($target ? $target::class : null);
        $entityId = $context['entity_id'] ?? ($target->id ?? null);
        $locationId = $context['location_id'] ?? $this->resolveLocationId($actor, $target);
        $result = $context['result'] ?? AuditResult::SUCCESS->value;
        $requestId = $context['request_id'] ?? $request->header('X-Request-Id')
            ?? $request->header('X-Request-ID')
            ?? $request->header('X-Trace-Id')
            ?? $request->header('X-Trace-ID');
        $idempotencyKey = $context['idempotency_key'] ?? $request->header('Idempotency-Key');
        $deviceId = $context['device_id'] ?? $request->header('X-Device-Id')
            ?? $request->header('X-Device-ID')
            ?? $request->input('device_id');
        $ipAddress = $request->ip();

        $meta = array_merge([
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'impersonation_session_id' => $this->impersonationContext->sessionId(),
        ], $meta);

        $log = AuditLog::create([
            'action' => $action,
            'risk_level' => $risk->value,
            'result' => $result,
            'actor_id' => $actor?->id,
            'impersonator_id' => $impersonator?->id,
            'location_id' => $locationId,
            'target_type' => $entityType,
            'target_id' => $entityId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'meta' => $meta,
            'snapshot_before' => $context['snapshot_before'] ?? null,
            'snapshot_after' => $context['snapshot_after'] ?? null,
            'ip_address' => $ipAddress,
            'ip_hash' => $ipAddress ? hash('sha256', $ipAddress) : null,
            'user_agent' => $request->userAgent(),
            'device_id' => $deviceId,
            'request_id' => $requestId,
            'idempotency_key' => $idempotencyKey,
        ]);

        IngestAuditLogLearningJob::dispatchAfterResponse($log->id);

        return $log;
    }

    private function resolveLocationId(?User $actor, ?object $target): ?int
    {
        if ($target) {
            if (isset($target->location_id)) {
                return $target->location_id;
            }

            if (isset($target->client_location_id)) {
                return $target->client_location_id;
            }
        }

        if ($actor?->isClient()) {
            return $actor->client_location_id;
        }

        return null;
    }
}
