<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SignhostService
{
    private string $baseUrl;
    private string $appKey;
    private string $userToken;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.signhost.base_url'), '/') . '/';
        $this->appKey = (string) config('services.signhost.app_key');
        $this->userToken = (string) config('services.signhost.user_token');
    }

    /**
     * @param array<int, array{email:string,name:string,send?:bool}> $signers
     */
    public function createTransaction(array $signers, string $pdfPath, string $reference): array
    {
        $payloadSigners = [];
        foreach ($signers as $signer) {
            $payloadSigners[] = [
                'Email' => $signer['email'],
                'ScribbleName' => $signer['name'],
                'SendSignRequest' => $signer['send'] ?? true,
            ];
        }

        $create = $this->request('post', 'transaction', [
            'Signers' => $payloadSigners,
            'SendEmailNotifications' => true,
            'Reference' => $reference,
        ]);

        $transactionId = $create['Id'] ?? $create['id'] ?? null;
        if (! $transactionId) {
            throw new \RuntimeException('Signhost transaction id missing');
        }

        $this->uploadFile($transactionId, $pdfPath);
        $this->startTransaction($transactionId);

        $transaction = $this->request('get', "transaction/{$transactionId}");

        return [
            'transaction_id' => $transactionId,
            'transaction' => $transaction,
        ];
    }

    public function createSingleSignerTransaction(array $signer, string $pdfPath, string $reference): array
    {
        return $this->createTransaction([$signer], $pdfPath, $reference);
    }

    public function getTransaction(string $transactionId): array
    {
        return $this->request('get', "transaction/{$transactionId}");
    }

    public function resendTransaction(string $transactionId): void
    {
        $this->request('put', "transaction/{$transactionId}/start");
    }

    public function cancelTransaction(string $transactionId): void
    {
        $this->request('put', "transaction/{$transactionId}/cancel");
    }

    public function downloadSignedFile(string $transactionId): ?string
    {
        $response = $this->requestRaw('get', "transaction/{$transactionId}/file/signed");
        if ($response['status'] !== 200) {
            return null;
        }

        return $response['body'];
    }

    public function verifyWebhook(array $payload, string $checksum): bool
    {
        $secret = (string) config('services.signhost.shared_secret');
        if ($secret === '') {
            return false;
        }

        $transactionId = (string) ($payload['TransactionId'] ?? $payload['transactionId'] ?? '');
        $status = (string) ($payload['Status'] ?? $payload['status'] ?? '');
        $fileId = '';
        if (! empty($payload['File']) && is_array($payload['File'])) {
            $fileId = (string) ($payload['File']['Id'] ?? $payload['File']['id'] ?? '');
        }

        if ($fileId !== '') {
            $source = $transactionId.'|'.$fileId.'|'.$status.'|'.$secret;
        } else {
            $source = $transactionId.'||'.$status.'|'.$secret;
        }

        $calculated = sha1($source);
        return hash_equals($calculated, $checksum);
    }

    private function uploadFile(string $transactionId, string $pdfPath): void
    {
        $contents = file_get_contents($pdfPath);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read contract PDF');
        }

        $digest = base64_encode(hash('sha256', $contents, true));

        $this->requestRaw('put', "transaction/{$transactionId}/file/Contract.pdf", [
            'headers' => [
                'Content-Type' => 'application/pdf',
                'Digest' => 'SHA-256='.$digest,
            ],
            'body' => $contents,
        ]);
    }

    private function startTransaction(string $transactionId): void
    {
        $this->request('put', "transaction/{$transactionId}/start");
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $options = [];
        if (! empty($payload)) {
            $options['json'] = $payload;
        }

        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->send(strtoupper($method), $this->baseUrl.ltrim($path, '/'), $options);

        if ($response->failed()) {
            Log::error('Signhost API error', ['path' => $path, 'body' => $response->body()]);
            throw new \RuntimeException('Signhost API error');
        }

        return $response->json();
    }

    private function requestRaw(string $method, string $path, array $options = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->send(strtoupper($method), $this->baseUrl.ltrim($path, '/'), $options);

        return [
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }

    private function headers(): array
    {
        return [
            'X-Auth-Client-Id' => $this->appKey,
            'X-Auth-Client-Token' => $this->userToken,
        ];
    }
}
