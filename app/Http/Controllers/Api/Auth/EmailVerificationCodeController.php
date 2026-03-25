<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResendVerificationRequest;
use App\Http\Requests\Api\VerifyEmailCodeRequest;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\EmailVerificationCodeService;
use Illuminate\Auth\Events\Verified;

class EmailVerificationCodeController extends Controller
{
    public function resend(
        ResendVerificationRequest $request,
        UserRepository $users,
        EmailVerificationCodeService $verificationCodes
    ) {
        $email = strtolower(trim((string) $request->validated('email')));
        $user = $users->findByEmail($email);

        if (! $user) {
            return response()->json([
                'message' => 'We could not find an account for that email address.',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'sent' => true,
                'verified' => true,
                'message' => 'This email address is already verified. Please log in.',
            ]);
        }

        $verificationCodes->issue($user);

        return response()->json([
            'sent' => true,
            'message' => $this->verificationDeliveryMessage(),
            'ttl_minutes' => $verificationCodes->ttlMinutes(),
        ]);
    }

    public function verify(
        VerifyEmailCodeRequest $request,
        UserRepository $users,
        EmailVerificationCodeService $verificationCodes
    ) {
        $validated = $request->validated();
        $email = strtolower(trim((string) $validated['email']));
        $user = $users->findByEmail($email);

        if (! $user) {
            return response()->json([
                'message' => 'We could not find an account for that email address.',
            ], 404);
        }

        if (! $user->isActive()) {
            return response()->json([
                'message' => 'Account is not active.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            if (! $verificationCodes->verify($user, (string) $validated['code'])) {
                return response()->json([
                    'message' => 'The verification code is invalid or has expired.',
                ], 422);
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $tokenName = isset($validated['device_name']) && is_string($validated['device_name']) && $validated['device_name'] !== ''
            ? $validated['device_name']
            : 'web';
        $token = $user->createToken($tokenName);
        $user->load(['locations', 'clientLocation']);

        return response()->json([
            'verified' => true,
            'token' => $token->plainTextToken,
            'user' => $this->presentUser($user),
            'message' => 'Email verified successfully.',
        ]);
    }

    private function presentUser(User $user): array
    {
        $resolvedLocation = $user->resolvedLocation();
        $resolvedLocationRole = $user->resolvedLocationRole();

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? 'client',
            'type' => $user->type?->value ?? null,
            'status' => $user->status?->value ?? null,
            'phone' => $user->phone,
            'location_id' => $user->location_id,
            'location_role' => $user->location_role,
            'client_location_id' => $user->client_location_id,
            'has_location_assignment' => $user->isClient()
                ? $user->client_location_id !== null
                : $user->locations->isNotEmpty(),
            'can_access_board' => $user->isAdmin() || ($user->isEmployee() && $user->locations->isNotEmpty()),
            'location' => $resolvedLocation ? [
                'id' => $resolvedLocation->id,
                'name' => $resolvedLocation->name,
                'code' => $resolvedLocation->code,
                'role' => $resolvedLocationRole,
            ] : null,
            'client_location' => $user->clientLocation ? [
                'id' => $user->clientLocation->id,
                'name' => $user->clientLocation->name,
                'code' => $user->clientLocation->code,
            ] : null,
            'locations' => $user->locations->map(static fn ($location) => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'role' => $location->pivot?->role,
            ])->values()->all(),
        ];
    }

    private function verificationDeliveryMessage(): string
    {
        return config('mail.default') === 'log'
            ? 'Verification code was written to storage/logs/laravel.log because MAIL_MAILER=log in local development.'
            : 'Verification code sent to your email.';
    }
}
