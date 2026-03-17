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
     * @param string|array<int, string> $pdfPaths  Single path or array of paths
     */
    public function createTransaction(array $signers, string|array $pdfPaths, string $reference): array
    {
        // Normalize to array for uniform handling
        $pdfPaths = is_array($pdfPaths) ? array_values($pdfPaths) : [$pdfPaths];

        $payloadSigners = [];
        foreach ($signers as $signer) {
            $payloadSigners[] = $this->buildSignerPayload($signer);
        }

        $create = $this->request('post', 'transaction', [
            'Seal' => false,
            'Signers' => $payloadSigners,
            'Reference' => $reference,
        ]);

        $transactionId = $create['Id'] ?? $create['id'] ?? null;
        if (! $transactionId) {
            throw new \RuntimeException('Signhost transaction id missing');
        }

        // Upload each PDF with a unique file key
        foreach ($pdfPaths as $index => $pdfPath) {
            $fileKey = count($pdfPaths) === 1
                ? 'Contract.pdf'
                : 'Contract_' . ($index + 1) . '.pdf';
            $this->uploadFile($transactionId, $pdfPath, $fileKey);
        }

        $this->startTransaction($transactionId);

        $transaction = $this->request('get', "transaction/{$transactionId}");

        return [
            'transaction_id' => $transactionId,
            'transaction' => $transaction,
        ];
    }

    public function createSingleSignerTransaction(array $signer, string|array $pdfPaths, string $reference): array
    {
        return $this->createTransaction([$signer], $pdfPaths, $reference);
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

    private function uploadFile(string $transactionId, string $pdfPath, string $fileKey = 'Contract.pdf'): void
    {
        $contents = file_get_contents($pdfPath);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read contract PDF: ' . $pdfPath);
        }

        $digest = base64_encode(hash('sha256', $contents, true));

        $this->requestRaw('put', "transaction/{$transactionId}/file/{$fileKey}", [
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
            ->withOptions([
                'curl' => [
                    CURLOPT_RESOLVE => ["api.signhost.com:443:83.96.205.231"]
                ]
            ])
            ->acceptJson()
            ->send(strtoupper($method), $this->baseUrl.ltrim($path, '/'), $options);

        if ($response->failed()) {
            $body = $response->body();
            Log::error('Signhost API error', ['path' => $path, 'body' => $body]);
            throw new \RuntimeException("Signhost API error ({$path}): " . ($body ?: 'Empty response'));
        }

        return $response->json() ?? [];
    }

    private function requestRaw(string $method, string $path, array $options = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->withOptions([
                'curl' => [
                    CURLOPT_RESOLVE => ["api.signhost.com:443:83.96.205.231"]
                ]
            ])
            ->send(strtoupper($method), $this->baseUrl.ltrim($path, '/'), $options);

        return [
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }

    private function headers(): array
    {
        if ($this->appKey === '' || $this->userToken === '') {
            throw new \RuntimeException('Signhost credentials are missing. Configure SIGNHOST_APP_KEY and SIGNHOST_USER_TOKEN.');
        }

        return [
            'Authorization' => 'APIKey '.$this->userToken,
            'Application' => 'APPKey '.$this->appKey,
        ];
    }

    /**
     * @param array{email:string,name:string,send?:bool,message?:string} $signer
     */
    private function buildSignerPayload(array $signer): array
    {
        $sendSignRequest = $signer['send'] ?? true;

        return array_filter([
            'Email' => $signer['email'],
            'SendSignRequest' => $sendSignRequest,
            'SignRequestMessage' => $sendSignRequest
                ? ($signer['message'] ?? 'Please review and sign this document.')
                : null,
            'Verifications' => [[
                'Type' => 'Scribble',
                'ScribbleName' => $signer['name'],
            ]],
        ], static fn ($value) => $value !== null);
    }
}
