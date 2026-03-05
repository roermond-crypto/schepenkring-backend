<?php

namespace App\Actions\Auth;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Services\ActionSecurity;

class LogoutUserAction
{
    public function __construct(private ActionSecurity $security)
    {
    }

    public function execute(User $user): void
    {
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        $this->security->log('auth.logout', RiskLevel::LOW, $user, $user, [
            'token_id' => $token?->id,
        ], [
            'location_id' => $user->client_location_id,
        ]);
    }
}
