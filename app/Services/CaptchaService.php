<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaptchaService
{
    public function verify(?string $token, ?string $ip): bool
    {
        if (!config('security.captcha.enabled')) {
            return true;
        }

        $secret = (string) config('security.captcha.secret');
        if ($secret === '' || !$token) {
            return false;
        }

        $provider = (string) config('security.captcha.provider', 'hcaptcha');
        $url = $provider === 'recaptcha'
            ? 'https://www.google.com/recaptcha/api/siteverify'
            : 'https://hcaptcha.com/siteverify';

        try {
            $response = Http::asForm()->timeout(5)->post($url, [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);

            if ($response->failed()) {
                Log::warning('Captcha verification failed', ['status' => $response->status()]);
                return false;
            }

            $data = $response->json();
            return (bool) ($data['success'] ?? false);
        } catch (\Throwable $e) {
            Log::warning('Captcha verification error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
