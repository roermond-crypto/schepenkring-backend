<?php

namespace App\Http\Controllers;

use App\Models\EmailVerificationToken;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\GooglePlaceLookupService;
use App\Services\OnboardingService;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class OnboardingController extends Controller
{
    public function registerUser(Request $request, EmailVerificationService $emailVerification, OnboardingService $onboarding)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'accept_terms' => 'accepted',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'Customer',
            'status' => 'email_pending',
            'access_level' => 'None',
            'registration_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'terms_accepted_at' => now(),
        ]);

        $this->ensureRole('Customer');
        $user->assignRole('Customer');

        $emailVerification->sendCode($user, $request, ['source' => 'register_user']);
        $onboarding->markStep($user, 'email_verify', $request);

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'role' => 'user',
            'status' => $user->status,
            'verification_required' => true,
        ], 201);
    }

    public function registerPartner(
        Request $request,
        GooglePlaceLookupService $places,
        EmailVerificationService $emailVerification,
        OnboardingService $onboarding
    ) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8|confirmed',
            'place_id' => 'required|string|max:255',
            'accept_terms' => 'accepted',
        ]);

        if (PartnerProfile::where('place_id', $validated['place_id'])->exists()) {
            return response()->json(['message' => 'Company already registered'], 422);
        }

        $placeDetails = $places->fetchByPlaceId($validated['place_id']);
        if (!empty($placeDetails['error'])) {
            return response()->json(['message' => 'Invalid company selection'], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'Partner',
            'status' => 'email_pending',
            'access_level' => 'Limited',
            'registration_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'terms_accepted_at' => now(),
            'phone_number' => $validated['phone'] ?? null,
        ]);

        $this->ensureRole('Partner');
        $user->assignRole('Partner');

        PartnerProfile::create([
            'user_id' => $user->id,
            'place_id' => $validated['place_id'],
            'company_name' => $placeDetails['company_name'] ?? 'Unknown company',
            'street' => $placeDetails['street'] ?? null,
            'postal_code' => $placeDetails['postal_code'] ?? null,
            'city' => $placeDetails['city'] ?? null,
            'country' => $placeDetails['country'] ?? null,
            'latitude' => $placeDetails['latitude'] ?? null,
            'longitude' => $placeDetails['longitude'] ?? null,
        ]);

        $emailVerification->sendCode($user, $request, ['source' => 'register_partner']);
        $onboarding->markStep($user, 'email_verify', $request);

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'role' => 'partner',
            'status' => $user->status,
            'verification_required' => true,
        ], 201);
    }

    public function verifyEmailInfo(string $token, EmailVerificationService $emailVerification)
    {
        $record = $emailVerification->findToken($token);
        if (!$record || $record->used_at) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 404);
        }

        $user = $record->user;
        if (!$user) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 404);
        }

        return response()->json([
            'email' => $user->email,
            'role' => strtolower((string) $user->role) === 'partner' ? 'partner' : 'user',
            'status' => $user->status,
            'expires_at' => $record->expires_at,
            'locked_until' => $record->locked_until,
            'expired' => $record->expires_at->isPast(),
        ]);
    }

    public function verifyEmailConfirm(
        Request $request,
        string $token,
        EmailVerificationService $emailVerification,
        OnboardingService $onboarding
    ) {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $record = $emailVerification->findToken($token);
        if (!$record || $record->used_at) {
            return response()->json(['message' => 'Invalid code, please try again'], 422);
        }

        $user = $record->user;
        if (!$user) {
            return response()->json(['message' => 'Invalid code, please try again'], 422);
        }

        $result = $emailVerification->verifyToken($record, $validated['code']);
        if (!$result['ok']) {
            $emailVerification->recordVerifyAttempt($user, $result['reason'], $request);
            return response()->json(['message' => 'Invalid code, please try again'], 422);
        }

        $user->email_verified_at = now();
        if (strtolower((string) $user->role) === 'partner') {
            $user->status = 'pending_agreement';
            $onboarding->markStep($user, 'agreement', $request);
        } else {
            $user->status = 'active';
            $onboarding->complete($user, $request);
        }
        $user->save();

        $emailVerification->recordVerifyAttempt($user, 'verified', $request);

        return response()->json([
            'message' => 'Email verified successfully',
            'status' => $user->status,
            'next_step' => $onboarding->nextStep($user),
        ]);
    }

    public function resendVerification(
        Request $request,
        string $token,
        EmailVerificationService $emailVerification
    ) {
        $record = $emailVerification->findToken($token);
        if (!$record || $record->used_at) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 404);
        }

        $user = $record->user;
        if (!$user) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified'], 200);
        }

        $data = $emailVerification->sendCode($user, $request, ['source' => 'resend']);

        return response()->json([
            'message' => 'A confirmation code has been sent to your email. Please check your inbox.',
            'verification_token' => $data['token'] ?? null,
            'verification_url' => $data['verification_url'] ?? null,
        ]);
    }

    public function changeEmail(
        Request $request,
        string $token,
        EmailVerificationService $emailVerification
    ) {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email'),
            ],
        ]);

        $record = $emailVerification->findToken($token);
        if (!$record || $record->used_at) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 404);
        }

        $user = $record->user;
        if (!$user || $user->email_verified_at || strtolower((string) $user->status) !== 'email_pending') {
            return response()->json(['message' => 'Email change not allowed'], 403);
        }

        $oldEmail = $user->email;
        $user->email = $validated['email'];
        $user->save();

        EmailVerificationToken::where('user_id', $user->id)->delete();

        $data = $emailVerification->sendCode($user, $request, ['source' => 'change_email']);

        SystemLogService::log(
            'email_changed_during_verification',
            'User',
            $user->id,
            ['email' => $oldEmail],
            ['email' => $user->email],
            "Email updated during verification for user {$user->id}",
            $request
        );

        return response()->json([
            'message' => 'Email updated. Please check your inbox.',
            'verification_token' => $data['token'] ?? null,
            'verification_url' => $data['verification_url'] ?? null,
        ]);
    }

    public function onboardingStatus(Request $request, OnboardingService $onboarding)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'status' => $user->status,
            'next_step' => $onboarding->nextStep($user),
        ]);
    }

    private function ensureRole(string $name): void
    {
        if (!Role::where('name', $name)->exists()) {
            Role::create(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
