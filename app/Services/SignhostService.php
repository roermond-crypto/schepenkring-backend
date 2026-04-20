<?php

namespace App\Services;

use App\Models\User;
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

    public function createMultiSignerTransaction(array $signers, string $pdfPath, string $reference): array
    {
        $signerPayload = [];
        foreach ($signers as $signer) {
            $signerPayload[] = [
                'Email' => $signer['email'],
                'ScribbleName' => $signer['name'],
                'SendSignRequest' => true,
                'SignRequestMessage' => $this->defaultSignRequestMessage(),
            ];
        }

        $create = $this->request('post', 'transaction', [
            'Signers' => $signerPayload,
            'SendEmailNotifications' => true,
            'SignRequestSubject' => $this->defaultSignRequestSubject(),
            'SignRequestMessage' => $this->defaultSignRequestMessage(),
            'Reference' => $reference,
        ]);

        $transactionId = $create['Id'] ?? $create['id'] ?? null;
        if (!$transactionId) {
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

    public function createSingleSignerTransaction(User $signer, string $pdfPath, string $reference): array
    {
        $create = $this->request('post', 'transaction', [
            'Signers' => [
                [
                    'Email' => $signer->email,
                    'ScribbleName' => $signer->name,
                    'SendSignRequest' => true,
                    'SignRequestMessage' => $this->defaultSignRequestMessage(),
                ],
            ],
            'SendEmailNotifications' => true,
            'SignRequestSubject' => $this->defaultSignRequestSubject(),
            'SignRequestMessage' => $this->defaultSignRequestMessage(),
            'Reference' => $reference,
        ]);

        $transactionId = $create['Id'] ?? $create['id'] ?? null;
        if (!$transactionId) {
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

    public function createVerificationPhaseTransaction(
        User $signer,
        array $metadata,
        ?string $pdfPath = null,
        ?string $reference = null
    ): array {
        $payload = [
            'Signers' => [
                [
                    'Email' => $signer->email,
                    'ScribbleName' => $signer->name,
                    'SendSignRequest' => true,
                    'SignRequestMessage' => $this->defaultSignRequestMessage(),
                ],
            ],
            'SendEmailNotifications' => true,
            'SignRequestSubject' => $this->defaultSignRequestSubject(),
            'SignRequestMessage' => $this->defaultSignRequestMessage(),
            'Reference' => $reference ?: ('verification-' . ($metadata['onboarding_id'] ?? $signer->id)),
        ];

        if (!empty($metadata)) {
            $payload['MetaData'] = $metadata;
        }

        $create = $this->request('post', 'transaction', $payload);
        $transactionId = $create['Id'] ?? $create['id'] ?? null;
        if (!$transactionId) {
            throw new \RuntimeException('Signhost transaction id missing');
        }

        if ($pdfPath) {
            $this->uploadFile($transactionId, $pdfPath);
        } else {
            $this->uploadPlaceholderFile($transactionId);
        }

        $this->startTransaction($transactionId);
        $transaction = $this->request('get', "transaction/{$transactionId}");
        $redirectUrl = $this->extractSigningUrl($transaction);

        return [
            'transaction_id' => $transactionId,
            'transaction' => $transaction,
            'redirect_url' => $redirectUrl,
        ];
    }

    public function getTransaction(string $transactionId): array
    {
        return $this->request('get', "transaction/{$transactionId}");
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
        if (!empty($payload['File']) && is_array($payload['File'])) {
            $fileId = (string) ($payload['File']['Id'] ?? $payload['File']['id'] ?? '');
        }

        if ($fileId !== '') {
            $source = $transactionId . '|' . $fileId . '|' . $status . '|' . $secret;
        } else {
            $source = $transactionId . '||' . $status . '|' . $secret;
        }

        $calculated = sha1($source);
        return hash_equals($calculated, $checksum);
    }

    private function uploadFile(string $transactionId, string $pdfPath): void
    {
        $contents = file_get_contents($pdfPath);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read PDF');
        }

        $digest = base64_encode(hash('sha256', $contents, true));

        $this->requestRaw('put', "transaction/{$transactionId}/file/Contract.pdf", [
            'headers' => [
                'Content-Type' => 'application/pdf',
                'Digest' => 'SHA-256=' . $digest,
            ],
            'body' => $contents,
        ]);
    }

    private function uploadPlaceholderFile(string $transactionId): void
    {
        $placeholder = "%PDF-1.4\n1 0 obj <</Type/Catalog/Pages 2 0 R>> endobj\n2 0 obj <</Type/Pages/Kids [3 0 R]/Count 1>> endobj\n3 0 obj <</Type/Page/Parent 2 0 R/MediaBox [0 0 595.28 841.89]/Contents 4 0 R>> endobj\n4 0 obj <</Length 44>> stream\nBT /F1 12 Tj 0 -12 Td (Identity Verification) ET\nendstream endobj\nxref\n0 5\n0000000000 65535 f\n0000000010 00000 n\n0000000060 00000 n\n0000000120 00000 n\n0000000220 00000 n\ntrailer <</Size 5/Root 1 0 R>>\nstartxref\n314\n%%EOF";
        
        $digest = base64_encode(hash('sha256', $placeholder, true));

        $this->requestRaw('put', "transaction/{$transactionId}/file/VerificationDocument.pdf", [
            'headers' => [
                'Content-Type' => 'application/pdf',
                'Digest' => 'SHA-256=' . $digest,
            ],
            'body' => $placeholder,
        ]);
    }

    private function startTransaction(string $transactionId): void
    {
        $this->request('put', "transaction/{$transactionId}/start");
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $options = [];
        if (!empty($payload)) {
            $options['json'] = $payload;
        }

        $response = Http::withoutVerifying()
            ->withHeaders($this->headers())
            ->acceptJson()
            ->send(strtoupper($method), $this->baseUrl . ltrim($path, '/'), $options);

        if ($response->failed()) {
            Log::error('Signhost API error', ['path' => $path, 'body' => $response->body()]);
            throw new \RuntimeException('Signhost API error: ' . $response->status());
        }

        return $response->json() ?? [];
    }

    private function requestRaw(string $method, string $path, array $options = []): array
    {
        $response = Http::withoutVerifying()
            ->withHeaders($this->headers())
            ->send(strtoupper($method), $this->baseUrl . ltrim($path, '/'), $options);

        return [
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }

    private function headers(): array
    {
        return [
            'Application' => 'APPKey ' . $this->appKey,
            'Authorization' => 'APIKey ' . $this->userToken,
        ];
    }

    private function defaultSignRequestSubject(): string
    {
        return config('services.signhost.sign_request_subject', 'Please sign your document');
    }

    private function defaultSignRequestMessage(): string
    {
        return config('services.signhost.sign_request_message', 'Please review and sign the attached document.');
    }

    private function extractSigningUrl(array $transaction): ?string
    {
        $signers = $transaction['Signers'] ?? $transaction['signers'] ?? [];
        if (!is_array($signers) || count($signers) === 0) {
            return null;
        }

        return $signers[0]['SignUrl'] ?? $signers[0]['signUrl'] ?? null;
    }
}
