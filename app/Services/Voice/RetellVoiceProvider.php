<?php

namespace App\Services\Voice;

use App\Contracts\VoiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Retell AI (https://www.retellai.com) voice provider.
 *
 * Implemented against Retell's documented v2 REST API. No live Retell
 * account/credentials existed at the time this was written — verify the
 * exact endpoint paths and payload shape against Retell's current API
 * reference before relying on this in production, and adjust the request
 * bodies below if their API has moved on since.
 */
class RetellVoiceProvider implements VoiceProvider
{
    public function name(): string
    {
        return 'retell';
    }

    public function usesMediaStreaming(): bool
    {
        return false;
    }

    public function initiateOutboundCall(array $payload): array
    {
        $body = array_filter([
            'from_number' => $payload['from'] ?? null,
            'to_number' => $payload['to'] ?? null,
            'override_agent_id' => $payload['agent_id'] ?? null,
            'retell_llm_dynamic_variables' => $payload['dynamic_variables'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ], fn ($value) => $value !== null);

        $response = $this->request('post', '/v2/create-phone-call', $body);

        return [
            'external_call_id' => $response['call_id'] ?? null,
            'raw' => $response,
        ];
    }

    public function answerCall(string $externalCallId): array
    {
        // Retell answers inbound calls to a configured number automatically —
        // there is no explicit "answer" action to call here.
        return ['status' => 'noop'];
    }

    public function hangupCall(string $externalCallId, ?string $reason = null): array
    {
        return $this->request('post', "/v2/end-call/{$externalCallId}", array_filter([
            'reason' => $reason,
        ]));
    }

    public function getCallStatus(string $externalCallId): array
    {
        return $this->request('get', "/v2/get-call/{$externalCallId}", []);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.retell.base_url', 'https://api.retellai.com'), '/');
    }

    private function apiKey(): ?string
    {
        $key = (string) config('services.retell.api_key');

        return $key !== '' ? $key : null;
    }

    private function request(string $method, string $path, array $payload): array
    {
        $apiKey = $this->apiKey();
        if (! $apiKey) {
            Log::warning('Retell API key missing');

            return [];
        }

        $url = $this->baseUrl().$path;

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(15)
            ->{$method}($url, $payload);

        if (! $response->successful()) {
            Log::warning('Retell request failed', [
                'status' => $response->status(),
                'url' => $url,
                'response' => $response->json(),
            ]);

            return [];
        }

        return $response->json() ?? [];
    }
}
