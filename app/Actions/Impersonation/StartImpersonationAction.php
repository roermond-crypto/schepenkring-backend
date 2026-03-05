<?php

namespace App\Actions\Impersonation;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Repositories\ImpersonationSessionRepository;
use App\Services\ActionSecurity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StartImpersonationAction
{
    public function __construct(
        private ImpersonationSessionRepository $sessions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, User $target, array $data, ?string $idempotencyKey): array
    {
        if (! Gate::forUser($actor)->allows('impersonate', $target)) {
            throw ValidationException::withMessages([
                'user' => 'You are not allowed to impersonate this user.',
            ]);
        }

        $this->security->assertFreshAuth($actor, $data['password'], $data['otp_code'] ?? null);
        $this->security->requireIdempotency($idempotencyKey, 'impersonation.start', $actor);

        $token = $target->createToken('impersonation:'.$actor->id, ['*']);

        $session = $this->sessions->create([
            'impersonator_id' => $actor->id,
            'impersonated_id' => $target->id,
            'token_id' => $token->accessToken->id,
            'started_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $this->security->log('impersonation.start', RiskLevel::HIGH, $actor, $target, [
            'session_id' => $session->id,
        ]);

        return [
            'token' => $token->plainTextToken,
            'session' => $session,
            'impersonated' => $target,
        ];
    }
}
