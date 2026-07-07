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

    /**
     * Create a transaction with multiple recipients and multiple PDF files.
     * Called by CreateSignhostRequestAction.
     *
     * @param array<int, array{email:string,name:string,role?:string,send?:bool}> $recipients
     * @param string[] $pdfPaths  absolute local paths
     */
    public function createTransaction(array $recipients, array $pdfPaths, string $reference): array
    {
        $signerPayload = [];
        foreach ($recipients as $recipient) {
            $signerPayload[] = [
                'Email' => $recipient['email'],
                'ScribbleName' => $recipient['name'],
                'SendSignRequest' => $recipient['send'] ?? true,
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
            throw new \RuntimeException('Signhost transaction id missing from create response');
        }

        foreach ($pdfPaths as $index => $pdfPath) {
            $label = count($pdfPaths) === 1 ? 'Contract.pdf' : "Contract_{$index}.pdf";
            $this->uploadFileByLabel($transactionId, $pdfPath, $label);
        }

        $this->startTransaction($transactionId);

        $transaction = $this->request('get', "transaction/{$transactionId}");

        return [
            'transaction_id' => $transactionId,
            'transaction' => $transaction,
        ];
    }

    /**
     * Delete (cancel) a Signhost transaction.
     * Called by CancelSignhostRequestAction.
     */
    public function cancelTransaction(string $transactionId): void
    {
        $response = $this->requestRaw('delete', "transaction/{$transactionId}");
        if (!in_array($response['status'], [200, 204, 404], true)) {
            Log::warning('SignhostService: cancelTransaction unexpected status', [
                'transaction_id' => $transactionId,
                'status' => $response['status'],
            ]);
        }
    }

    /**
     * Resend sign request emails for an existing transaction.
     * Called by ResendSignhostRequestAction.
     * Signhost does not have a dedicated "resend" endpoint — we retrieve the
     * transaction to confirm it is still active and then re-send via the
     * sign request PUT endpoint.
     */
    public function resendTransaction(string $transactionId): array
    {
        // Retrieve the current transaction to validate it is still sendable.
        $transaction = $this->request('get', "transaction/{$transactionId}");

        $status = strtolower((string) ($transaction['Status'] ?? $transaction['status'] ?? ''));
        if (in_array($status, ['signed', 'cancelled', 'failed', 'expired'], true)) {
            throw new \RuntimeException("Cannot resend a transaction with status: {$status}");
        }

        // Re-send the sign request (PUT …/start re-triggers emails for unsigned signers).
        $this->request('put', "transaction/{$transactionId}/start");

        return $transaction;
    }

    /**
     * Retrieve a fresh copy of the transaction from Signhost and extract
     * signing URLs, participant statuses, and expiry date.
     * Used by refreshYachtSignhostStatus and the resync endpoint.
     */
    public function resyncTransaction(string $transactionId): array
    {
        $transaction = $this->request('get', "transaction/{$transactionId}");

        $signers = $transaction['Signers'] ?? $transaction['signers'] ?? [];
        $buyerUrl = null;
        $sellerUrl = null;

        foreach ($signers as $index => $signer) {
            $url = $signer['SignUrl'] ?? $signer['signUrl'] ?? null;
            if ($index === 0) {
                $buyerUrl = $url;
            } elseif ($index === 1) {
                $sellerUrl = $url;
            }
        }

        $expiresOn = $transaction['ExpiresOn'] ?? $transaction['expiresOn'] ?? null;

        return [
            'transaction'   => $transaction,
            'transaction_id' => $transactionId,
            'buyer_url'     => $buyerUrl,
            'seller_url'    => $sellerUrl,
            'expires_at'    => $expiresOn,
            'status'        => $transaction['Status'] ?? $transaction['status'] ?? null,
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
        $this->uploadFileByLabel($transactionId, $pdfPath, 'Contract.pdf');
    }

    private function uploadFileByLabel(string $transactionId, string $pdfPath, string $label): void
    {
        $contents = file_get_contents($pdfPath);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read PDF: ' . $pdfPath);
        }

        $digest = base64_encode(hash('sha256', $contents, true));

        $this->requestRaw('put', "transaction/{$transactionId}/file/{$label}", [
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
