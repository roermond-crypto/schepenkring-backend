<?php

namespace App\Services;

use App\Models\OnboardingSession;
use App\Models\User;
use Illuminate\Http\Request;

class OnboardingService
{
    public function markStep(User $user, string $step, Request $request, bool $completed = false): OnboardingSession
    {
        $session = OnboardingSession::firstOrCreate(
            ['user_id' => $user->id],
            ['current_step' => $step]
        );

        $session->current_step = $step;
        $session->last_step_at = now();
        $session->ip_address = $request->ip();
        $session->user_agent = $request->userAgent();
        if ($completed) {
            $session->completed = true;
        }
        $session->save();

        return $session;
    }

    public function complete(User $user, Request $request): OnboardingSession
    {
        return $this->markStep($user, 'completed', $request, true);
    }

    public function nextStep(User $user): ?string
    {
        $status = strtolower((string) $user->status);
        $role = strtolower((string) $user->role);

        if ($status === 'active') {
            return null;
        }

        if ($status === 'email_pending') {
            return '/verify-email';
        }

        if ($role === 'partner') {
            if ($status === 'pending_agreement') {
                return '/partner/agreement';
            }
            if ($status === 'contract_pending') {
                return '/partner/contract-signing';
            }
        }

        return '/verify-email';
    }
}
