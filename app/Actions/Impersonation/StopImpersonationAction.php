<?php

namespace App\Actions\Impersonation;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Repositories\ImpersonationSessionRepository;
use App\Services\ActionSecurity;
use Illuminate\Validation\ValidationException;

class StopImpersonationAction
{
    public function __construct(
        private ImpersonationSessionRepository $sessions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, int $tokenId, ?string $idempotencyKey): array
    {
        $session = $this->sessions->findActiveByTokenId($tokenId);

        if (! $session) {
            throw ValidationException::withMessages([
                'impersonation' => 'No active impersonation session found.',
            ]);
        }

        $this->security->requireIdempotency($idempotencyKey, 'impersonation.stop', $actor);

        $session->forceFill([
            'ended_at' => now(),
        ])->save();

        $session->token?->delete();

        $impersonator = $session->impersonator;
        $token = $impersonator->createToken('impersonation-stop');

        $this->security->log('impersonation.stop', RiskLevel::HIGH, $actor, $impersonator, [
            'session_id' => $session->id,
        ], [
            'location_id' => $impersonator->client_location_id,
        ]);

        return [
            'token' => $token->plainTextToken,
            'impersonator' => $impersonator,
        ];
    }
}
