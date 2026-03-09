<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelnyxService
{
    private function baseUrl(): string
    {
        return rtrim((string) config('services.telnyx.base_url', 'https://api.telnyx.com/v2'), '/');
    }

    private function apiKey(): ?string
    {
        $key = (string) config('services.telnyx.api_key');

        return $key !== '' ? $key : null;
    }

    public function answerCall(string $callControlId): array
    {
        return $this->request('post', "/calls/{$callControlId}/actions/answer", []);
    }

    public function hangupCall(string $callControlId, ?string $reason = null): array
    {
        $payload = [];
        if ($reason) {
            $payload['reason'] = $reason;
        }

        return $this->request('post', "/calls/{$callControlId}/actions/hangup", $payload);
    }

    public function startStreaming(string $callControlId, string $streamUrl, ?string $track = null): array
    {
        $payload = ['stream_url' => $streamUrl];
        if ($track) {
            $payload['stream_track'] = $track;
        }

        return $this->request('post', "/calls/{$callControlId}/actions/streaming_start", $payload);
    }

    public function initiateCall(array $payload): array
    {
        return $this->request('post', '/calls', $payload);
    }

    private function request(string $method, string $path, array $payload): array
    {
        $apiKey = $this->apiKey();
        if (! $apiKey) {
            Log::warning('Telnyx API key missing');

            return [];
        }

        $url = $this->baseUrl().$path;

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(15)
            ->{$method}($url, $payload);

        if (! $response->successful()) {
            Log::warning('Telnyx request failed', [
                'status' => $response->status(),
                'url' => $url,
                'response' => $response->json(),
            ]);

            return [];
        }

        return $response->json() ?? [];
    }
}
