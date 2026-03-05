<?php

namespace App\Actions\Auth;

use App\Models\User;

class LogoutUserAction
{
    public function execute(User $user): void
    {
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }
    }
}
