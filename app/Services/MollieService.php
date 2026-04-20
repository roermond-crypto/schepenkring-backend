<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MollieService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.mollie.base_url', 'https://api.mollie.com/v2'), '/');
        $this->apiKey = $this->resolveKey();
    }

    public function createPayment(array $payload, ?string $idempotencyKey = null): array
    {
        $this->ensureConfigured();
        $request = $this->newRequest();
        if ($idempotencyKey) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $request->post($this->baseUrl . '/payments', $payload);
        
        if ($response->failed()) {
            Log::error('Mollie create payment failed', ['body' => $response->body()]);
            throw new \RuntimeException($this->messageForFailedResponse($response->json(), 'create'));
        }

        return $response->json();
    }

    public function getPayment(string $paymentId): array
    {
        $this->ensureConfigured();
        $response = $this->newRequest()->get($this->baseUrl . '/payments/' . $paymentId);

        if ($response->failed()) {
            Log::error('Mollie get payment failed', ['body' => $response->body()]);
            throw new \RuntimeException($this->messageForFailedResponse($response->json(), 'fetch'));
        }

        return $response->json();
    }

    private function resolveKey(): string
    {
        $explicit = (string) config('services.mollie.api_key');
        if ($explicit !== '') {
            return $explicit;
        }

        $env = env('APP_ENV');
        if ($env === 'production') {
            return (string) config('services.mollie.api_key_live');
        }

        return (string) config('services.mollie.api_key_test');
    }

    private function newRequest(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::withToken($this->apiKey)->acceptJson();

        if (config('app.env') !== 'production') {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    private function ensureConfigured(): void
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('Mollie is not configured. Set MOLLIE_API_KEY or MOLLIE_API_KEY_TEST.');
        }
    }

    private function messageForFailedResponse(?array $payload, string $action): string
    {
        $detail = trim((string) data_get($payload, 'detail', ''));
        $title = trim((string) data_get($payload, 'title', ''));
        $combined = trim($title . ' ' . $detail);

        if (str_contains(strtolower($combined), 'invalid authorization header')) {
            return 'Mollie rejected the payment credentials. Check MOLLIE_API_KEY or MOLLIE_API_KEY_TEST.';
        }

        if ($detail !== '') {
            return "Unable to {$action} payment: {$detail}";
        }

        return "Unable to {$action} payment right now.";
    }
}
