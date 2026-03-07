<?php

namespace App\Services;

use App\Models\CopilotAction;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class CopilotActionTokenService
{
    public function issue(User $user, CopilotAction $action, array $payload, int $ttlSeconds = 600): string
    {
        $data = [
            'user_id' => $user->id,
            'action_id' => $action->action_id,
            'payload' => $payload,
            'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String(),
        ];

        return Crypt::encryptString(json_encode($data));
    }

    public function decode(string $token): ?array
    {
        try {
            $json = Crypt::decryptString($token);
            $data = json_decode($json, true);
            if (!is_array($data)) {
                return null;
            }

            $expiresAt = $data['expires_at'] ?? null;
            if ($expiresAt && now()->greaterThan($expiresAt)) {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
