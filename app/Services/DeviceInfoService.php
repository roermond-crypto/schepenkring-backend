<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceInfoService
{
    public function resolveDeviceId(Request $request): string
    {
        $deviceId = (string) ($request->header('X-Device-Id')
            ?? $request->input('device_id')
            ?? '');

        if ($deviceId === '') {
            $deviceId = (string) Str::uuid();
        }

        return $deviceId;
    }

    public function resolveDeviceName(Request $request): ?string
    {
        $deviceName = (string) ($request->header('X-Device-Name')
            ?? $request->input('device_name')
            ?? '');

        return $deviceName !== '' ? $deviceName : null;
    }

    public function parseUserAgent(?string $userAgent): array
    {
        $ua = $userAgent ?? '';
        $browser = 'Unknown';
        $os = 'Unknown';

        if (stripos($ua, 'Edg/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (stripos($ua, 'Chrome/') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Safari/') !== false) {
            $browser = 'Safari';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $browser = 'Firefox';
        }

        if (stripos($ua, 'Windows NT') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Mac OS X') !== false && stripos($ua, 'iPhone') === false && stripos($ua, 'iPad') === false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        return [
            'browser' => $browser,
            'os' => $os,
        ];
    }

    public function resolveLocation(Request $request): array
    {
        $country = $this->firstHeader($request, ['CF-IPCountry', 'X-Country', 'X-NS-Country']);
        $city = $this->firstHeader($request, ['CF-IPCity', 'X-City', 'X-NS-City']);
        $asn = $this->firstHeader($request, ['CF-ASN', 'X-ASN', 'X-NS-ASN']);

        return [
            'country' => $country ?: null,
            'city' => $city ?: null,
            'asn' => $asn ?: null,
        ];
    }

    private function firstHeader(Request $request, array $names): ?string
    {
        foreach ($names as $name) {
            $value = $request->header($name);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
