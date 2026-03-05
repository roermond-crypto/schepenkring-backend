<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;

class SessionDeviceService
{
    public function __construct(private DeviceInfoService $deviceInfo)
    {
    }

    public function buildContext(Request $request, string $deviceId): array
    {
        $userAgent = $request->userAgent();
        $parsed = $this->deviceInfo->parseUserAgent($userAgent);
        $location = $this->deviceInfo->resolveLocation($request);

        return [
            'device_id' => $deviceId,
            'device_name' => $this->deviceInfo->resolveDeviceName($request),
            'user_agent' => $userAgent,
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'ip_address' => $request->ip(),
            'ip_country' => $location['country'],
            'ip_city' => $location['city'],
            'ip_asn' => $location['asn'],
        ];
    }

    public function isDeviceBlocked(User $user, string $deviceId): bool
    {
        return UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->whereNotNull('blocked_at')
            ->exists();
    }

    public function upsertDevice(User $user, array $context): UserDevice
    {
        $device = UserDevice::firstOrNew([
            'user_id' => $user->id,
            'device_id' => $context['device_id'],
        ]);

        if (!$device->exists) {
            $device->first_seen_at = now();
        }

        $device->device_name = $context['device_name'] ?? $device->device_name;
        $device->browser = $context['browser'] ?? $device->browser;
        $device->os = $context['os'] ?? $device->os;
        $device->user_agent = $context['user_agent'] ?? $device->user_agent;
        $device->last_seen_at = now();
        $device->last_ip_address = $context['ip_address'] ?? $device->last_ip_address;
        $device->last_ip_country = $context['ip_country'] ?? $device->last_ip_country;
        $device->last_ip_city = $context['ip_city'] ?? $device->last_ip_city;
        $device->last_ip_asn = $context['ip_asn'] ?? $device->last_ip_asn;

        $device->save();

        return $device;
    }

    public function attachToken($token, array $context, string $authStrength): void
    {
        $token->device_id = $context['device_id'] ?? null;
        $token->device_name = $context['device_name'] ?? null;
        $token->browser = $context['browser'] ?? null;
        $token->os = $context['os'] ?? null;
        $token->user_agent = $context['user_agent'] ?? null;
        $token->ip_address = $context['ip_address'] ?? null;
        $token->ip_country = $context['ip_country'] ?? null;
        $token->ip_city = $context['ip_city'] ?? null;
        $token->ip_asn = $context['ip_asn'] ?? null;
        $token->auth_strength = $authStrength;
        $token->first_seen_at = $token->first_seen_at ?? now();
        $token->last_seen_at = now();
        $token->last_verified_at = now();
        $token->save();
    }

    public function createToken(User $user, string $authStrength, array $context): array
    {
        $expiresAt = null;
        $ttlMinutes = config('security.tokens.access_ttl_minutes');
        if (!empty($ttlMinutes)) {
            $expiresAt = now()->addMinutes((int) $ttlMinutes);
        }

        $tokenResult = $user->createToken('terminal_access_token', ['*'], $expiresAt);
        $this->attachToken($tokenResult->accessToken, $context, $authStrength);
        $this->upsertDevice($user, $context);

        return [
            'plainTextToken' => $tokenResult->plainTextToken,
            'token' => $tokenResult->accessToken,
        ];
    }
}
