<?php

namespace App\Http\Controllers;

use App\Models\OtpChallenge;
use App\Models\UserDevice;
use App\Services\DeviceInfoService;
use App\Services\OtpService;
use App\Services\SessionDeviceService;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SecurityController extends Controller
{
    public function verifyLoginOtp(
        Request $request,
        OtpService $otpService,
        SessionDeviceService $sessions,
        DeviceInfoService $deviceInfo
    ) {
        $validated = $request->validate([
            'otp_challenge_id' => 'required|string',
            'code' => 'required|string|size:6',
            'device_id' => 'nullable|string',
        ]);

        $challenge = OtpChallenge::where('id', $validated['otp_challenge_id'])
            ->where('purpose', 'login')
            ->first();

        if (!$challenge) {
            return response()->json(['message' => 'Invalid verification session.'], 404);
        }

        $rateKey = 'otp:verify:' . $challenge->id . ':' . $request->ip();
        $max = (int) config('security.otp.max_verify_attempts', 10);
        $overLimit = RateLimiter::tooManyAttempts($rateKey, $max);
        if ($overLimit && $challenge->user) {
            SystemLogService::logOtpEvent('verify_rate_limited', $challenge->user, $request, [
                'rate_key' => $rateKey,
                'max_attempts' => $max,
            ]);
        }
        RateLimiter::hit($rateKey, 15 * 60);

        if (!$otpService->verifyChallenge($challenge, $validated['code'])) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $user = $challenge->user;
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if (!$user->email_verified_at) {
            return response()->json(['message' => 'Email not verified.'], 403);
        }
        if ($user->status && strtolower((string) $user->status) !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        $deviceId = $validated['device_id'] ?? $challenge->device_id ?? $deviceInfo->resolveDeviceId($request);
        if ($sessions->isDeviceBlocked($user, $deviceId)) {
            return response()->json(['message' => 'This device is blocked.'], 403);
        }
        $context = $sessions->buildContext($request, $deviceId);

        $tokenData = $sessions->createToken($user, 'otp', $context);
        SystemLogService::logOtpEvent('verified', $user, $request, [
            'challenge_id' => $challenge->id,
            'device_id' => $deviceId,
        ]);
        SystemLogService::logSessionEvent('created', $user, $request, [
            'device_id' => $deviceId,
            'auth_strength' => 'otp',
        ]);

        return response()->json([
            'token' => $tokenData['plainTextToken'],
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'userType' => $user->role,
            'status' => $user->status,
            'access_level' => $user->access_level,
            'permissions' => $user->getPermissionNames(),
            'device_id' => $deviceId,
        ]);
    }

    public function resendLoginOtp(Request $request, OtpService $otpService)
    {
        $validated = $request->validate([
            'otp_challenge_id' => 'required|string',
        ]);

        $challenge = OtpChallenge::where('id', $validated['otp_challenge_id'])
            ->where('purpose', 'login')
            ->first();

        if (!$challenge || $challenge->used_at || $challenge->expires_at->isPast()) {
            return response()->json(['message' => 'Verification session expired. Please login again.'], 410);
        }

        $user = $challenge->user;
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $rateKey = 'otp:send:' . $user->id . ':' . $request->ip();
        $max = (int) config('security.otp.max_send_per_window', 3);
        $window = (int) config('security.otp.send_window_minutes', 15);
        $overLimit = RateLimiter::tooManyAttempts($rateKey, $max);
        if ($overLimit) {
            SystemLogService::logOtpEvent('send_rate_limited', $user, $request, [
                'rate_key' => $rateKey,
                'window_minutes' => $window,
                'max_per_window' => $max,
            ]);
        }
        RateLimiter::hit($rateKey, $window * 60);

        $challengeData = $otpService->createChallenge($user, 'login', $request, [
            'device_id' => $challenge->device_id,
            'reasons' => $challenge->metadata['reasons'] ?? [],
        ]);

        SystemLogService::logOtpEvent('resent', $user, $request, [
            'challenge_id' => $challengeData['challenge']->id,
            'device_id' => $challenge->device_id,
        ]);

        return response()->json([
            'otp_challenge_id' => $challengeData['challenge']->id,
            'otp_ttl_minutes' => $challengeData['ttl_minutes'],
            'message' => 'Verification code resent.',
        ]);
    }

    public function sendStepUpOtp(Request $request, OtpService $otpService)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $rateKey = 'otp:send:' . $user->id . ':' . $request->ip();
        $max = (int) config('security.otp.max_send_per_window', 3);
        $window = (int) config('security.otp.send_window_minutes', 15);
        $overLimit = RateLimiter::tooManyAttempts($rateKey, $max);
        if ($overLimit) {
            SystemLogService::logOtpEvent('send_rate_limited', $user, $request, [
                'rate_key' => $rateKey,
                'window_minutes' => $window,
                'max_per_window' => $max,
            ]);
        }
        RateLimiter::hit($rateKey, $window * 60);

        $challengeData = $otpService->createChallenge($user, 'step_up', $request, [
            'device_id' => $token->device_id,
            'token_id' => $token->id,
        ]);

        SystemLogService::logOtpEvent('sent', $user, $request, [
            'challenge_id' => $challengeData['challenge']->id,
            'device_id' => $token->device_id,
            'token_id' => $token->id,
            'purpose' => 'step_up',
        ]);

        return response()->json([
            'otp_challenge_id' => $challengeData['challenge']->id,
            'otp_ttl_minutes' => $challengeData['ttl_minutes'],
            'message' => 'Verification code sent.',
        ], 202);
    }

    public function verifyStepUpOtp(Request $request, OtpService $otpService)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'otp_challenge_id' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $challenge = OtpChallenge::where('id', $validated['otp_challenge_id'])
            ->where('purpose', 'step_up')
            ->where('user_id', $user->id)
            ->first();

        if (!$challenge) {
            return response()->json(['message' => 'Invalid verification session.'], 404);
        }

        $rateKey = 'otp:verify:' . $challenge->id . ':' . $request->ip();
        $max = (int) config('security.otp.max_verify_attempts', 10);
        $overLimit = RateLimiter::tooManyAttempts($rateKey, $max);
        if ($overLimit) {
            SystemLogService::logOtpEvent('verify_rate_limited', $user, $request, [
                'rate_key' => $rateKey,
                'max_attempts' => $max,
            ]);
        }
        RateLimiter::hit($rateKey, 15 * 60);

        if (!$otpService->verifyChallenge($challenge, $validated['code'])) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $token->auth_strength = $this->maxAuthStrength($token->auth_strength, 'otp');
        $token->last_verified_at = now();
        $token->save();

        SystemLogService::logOtpEvent('verified', $user, $request, [
            'challenge_id' => $challenge->id,
            'token_id' => $token->id,
            'purpose' => 'step_up',
        ]);
        SystemLogService::logSessionEvent('verified', $user, $request, [
            'token_id' => $token->id,
            'auth_strength' => $token->auth_strength,
        ]);

        return response()->json([
            'message' => 'Verification complete.',
            'auth_strength' => $token->auth_strength,
            'last_verified_at' => $token->last_verified_at,
        ]);
    }

    private function maxAuthStrength(?string $current, string $desired): string
    {
        $order = [
            'password' => 1,
            'otp' => 2,
            'mfa' => 3,
            'passkey' => 4,
        ];

        $currentRank = $order[$current ?? 'password'] ?? 1;
        $desiredRank = $order[$desired] ?? 2;

        return $currentRank >= $desiredRank ? ($current ?? 'password') : $desired;
    }

    public function devices(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $devices = UserDevice::where('user_id', $user->id)
            ->orderByDesc('last_seen_at')
            ->get();

        $tokens = $user->tokens()->orderByDesc('last_used_at')->get();
        $tokensByDevice = $tokens->groupBy('device_id');

        $payload = $devices->map(function (UserDevice $device) use ($tokensByDevice) {
            $sessions = $tokensByDevice->get($device->device_id, collect())
                ->map(fn ($token) => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'auth_strength' => $token->auth_strength,
                    'ip_address' => $token->ip_address,
                    'ip_country' => $token->ip_country,
                    'ip_city' => $token->ip_city,
                    'browser' => $token->browser,
                    'os' => $token->os,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                ]);

            return [
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'browser' => $device->browser,
                'os' => $device->os,
                'first_seen_at' => $device->first_seen_at,
                'last_seen_at' => $device->last_seen_at,
                'last_ip_address' => $device->last_ip_address,
                'last_ip_country' => $device->last_ip_country,
                'last_ip_city' => $device->last_ip_city,
                'last_ip_asn' => $device->last_ip_asn,
                'blocked_at' => $device->blocked_at,
                'sessions' => $sessions,
            ];
        });

        return response()->json(['devices' => $payload]);
    }

    public function revokeSession(Request $request, $tokenId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = $user->tokens()->where('id', $tokenId)->first();
        if (!$token) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $token->delete();
        SystemLogService::logSessionEvent('revoked', $user, $request, [
            'token_id' => $tokenId,
            'device_id' => $token->device_id,
        ]);

        return response()->json(['message' => 'Session revoked']);
    }

    public function logoutAll(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user->tokens()->delete();
        SystemLogService::logSessionEvent('revoked_all', $user, $request, []);

        return response()->json(['message' => 'All sessions revoked']);
    }

    public function blockDevice(Request $request, $deviceId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $device = UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $device->blocked_at = now();
        $device->save();

        $user->tokens()->where('device_id', $deviceId)->delete();
        SystemLogService::logDeviceEvent('blocked', $user, $request, [
            'device_id' => $deviceId,
        ]);

        return response()->json(['message' => 'Device blocked']);
    }

    public function unblockDevice(Request $request, $deviceId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $device = UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $device->blocked_at = null;
        $device->save();

        SystemLogService::logDeviceEvent('unblocked', $user, $request, [
            'device_id' => $deviceId,
        ]);

        return response()->json(['message' => 'Device unblocked']);
    }
}
