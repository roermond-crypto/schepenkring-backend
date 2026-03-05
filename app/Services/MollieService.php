<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MollieService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.mollie.base_url'), '/');
        $this->apiKey = $this->resolveKey();
    }

    public function createPayment(array $payload, ?string $idempotencyKey = null): array
    {
        $request = Http::withToken($this->apiKey)->acceptJson();
        if ($idempotencyKey) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $request->post($this->baseUrl . '/payments', $payload);

        if ($response->failed()) {
            Log::error('Mollie create payment failed', ['body' => $response->body()]);
            throw new \RuntimeException('Mollie create payment failed');
        }

        return $response->json();
    }

    public function getPayment(string $paymentId): array
    {
        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->get($this->baseUrl . '/payments/' . $paymentId);

        if ($response->failed()) {
            Log::error('Mollie get payment failed', ['body' => $response->body()]);
            throw new \RuntimeException('Mollie get payment failed');
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
}
