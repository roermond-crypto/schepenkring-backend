<?php

namespace App\Actions\Auth;

use App\Enums\AuditResult;
use App\Enums\RiskLevel;
use App\Repositories\UserRepository;
use App\Services\ActionSecurity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    public function __construct(
        private UserRepository $users,
        private ActionSecurity $security
    )
    {
    }

    public function execute(array $data): array
    {
        $user = $this->users->findByEmail($data['email']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->security->log('auth.login', RiskLevel::MEDIUM, $user, $user, [
                'email' => $data['email'],
                'reason' => 'invalid_credentials',
            ], [
                'result' => AuditResult::FAIL->value,
            ]);
            throw ValidationException::withMessages([
                'email' => 'auth.invalid_credentials',
            ]);
        }

        // Block accounts awaiting admin approval before checking email verification.
        if (in_array($user->status, [\App\Enums\UserStatus::PENDING_APPROVAL, \App\Enums\UserStatus::PENDING], true)) {
            $this->security->log('auth.login', RiskLevel::MEDIUM, $user, $user, [
                'email' => $data['email'],
                'reason' => 'pending_approval',
            ], [
                'result' => AuditResult::FAIL->value,
            ]);
            throw ValidationException::withMessages([
                'email' => 'auth.pending_approval',
            ]);
        }

        if (! $user->isActive()) {
            $this->security->log('auth.login', RiskLevel::MEDIUM, $user, $user, [
                'email' => $data['email'],
                'reason' => 'inactive',
            ], [
                'result' => AuditResult::FAIL->value,
            ]);
            throw ValidationException::withMessages([
                'email' => 'auth.account_inactive',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $this->security->log('auth.login', RiskLevel::MEDIUM, $user, $user, [
                'email' => $data['email'],
                'reason' => 'email_not_verified',
            ], [
                'result' => AuditResult::FAIL->value,
            ]);
            throw ValidationException::withMessages([
                'email' => 'auth.email_not_verified',
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $tokenName = $data['device_name'] ?? 'api';
        $token = $user->createToken($tokenName);

        $this->security->log('auth.login', RiskLevel::MEDIUM, $user, $user, [
            'device_name' => $tokenName,
        ], [
            'location_id' => $user->client_location_id,
            'result' => AuditResult::SUCCESS->value,
            'snapshot_after' => $user->fresh()->toArray(),
        ]);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }
}
