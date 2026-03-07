<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Ga4MeasurementService
{
    public function sendEvent(string $eventName, array $params, ?string $clientId = null, ?string $userId = null): bool
    {
        $measurementId = config('services.ga4.measurement_id');
        $apiSecret = config('services.ga4.api_secret');

        if (!$measurementId || !$apiSecret) {
            return false;
        }

        $clientId = $clientId ?: $this->generateClientId();

        $payload = [
            'client_id' => $clientId,
            'events' => [
                [
                    'name' => $eventName,
                    'params' => $this->sanitizeParams($params),
                ],
            ],
        ];

        if ($userId) {
            $payload['user_id'] = (string) $userId;
        }

        $url = 'https://www.google-analytics.com/mp/collect?measurement_id='
            . urlencode($measurementId) . '&api_secret=' . urlencode($apiSecret);

        try {
            $response = Http::timeout(5)->post($url, $payload);

            if ($response->failed()) {
                Log::warning('GA4 measurement failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
        } catch (\Throwable $e) {
            Log::warning('GA4 measurement exception', ['error' => $e->getMessage()]);
            return false;
        }

        return true;
    }

    private function sanitizeParams(array $params): array
    {
        $clean = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $clean[$key] = $value ? 1 : 0;
                continue;
            }
            if (is_int($value) || is_float($value)) {
                $clean[$key] = $value;
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    private function generateClientId(): string
    {
        return random_int(1000000000, 9999999999) . '.' . time();
    }
}
