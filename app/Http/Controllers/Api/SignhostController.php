<?php

namespace App\Http\Controllers\Api;

use App\Actions\Kyc\CreateKycCaseAction;
use App\Actions\Signhost\CancelSignhostRequestAction;
use App\Actions\Signhost\CreateSignhostRequestAction;
use App\Actions\Signhost\GenerateContractAction;
use App\Actions\Signhost\GetSignhostStatusAction;
use App\Actions\Signhost\ListSignhostDocumentsAction;
use App\Actions\Signhost\ResendSignhostRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Signhost\SignhostGenerateContractRequest;
use App\Http\Requests\Api\Signhost\SignhostRequestActionRequest;
use App\Http\Requests\Api\Signhost\SignhostRequestCreateRequest;
use App\Http\Requests\Api\Signhost\SignhostRequestQuery;
use App\Models\Deal;
use App\Models\KycCase;
use App\Models\Yacht;
use App\Http\Resources\SignDocumentResource;
use App\Http\Resources\SignRequestResource;
use App\Models\SignRequest;
use App\Services\SignhostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SignhostController extends Controller
{
    public function generateContract(
        SignhostGenerateContractRequest $request,
        GenerateContractAction $action,
        CreateSignhostRequestAction $createAction
    )
    {
        $validated = $request->validated();

        // Blocking KYC check — prevent contract generation if a KYC case is blocking
        $this->abortIfKycBlocking($validated);

        $signRequest = $action->execute($request->user(), $validated);
        $signRequest = $this->maybeCreateSignhost($request, $signRequest, $validated, $createAction);
        $metadata = $signRequest->metadata ?? [];

        // Auto-create KYC from the deal/entity when contract is started
        $this->autoCreateKycFromContract($validated);

        return response()->json([
            'message' => $signRequest->signhost_transaction_id ? 'Contract generated and Signhost request created' : 'Contract generated',
            'contract_pdf_path' => $metadata['contract_pdf_path'] ?? null,
            'contract_sha256' => $metadata['contract_sha256'] ?? null,
            'contract_pdf_paths' => $metadata['contract_pdf_paths'] ?? [],
            'contract_sha256s' => $metadata['contract_sha256s'] ?? [],
            'sign_url' => $signRequest->sign_url,
            'sign_request' => new SignRequestResource($signRequest),
        ]);
    }

    public function requestSignhost(SignhostRequestCreateRequest $request, CreateSignhostRequestAction $action)
    {
        $signRequest = $action->execute(
            $request->user(),
            $request->validated(),
            $request->header('Idempotency-Key') ?? $request->input('idempotency_key')
        );

        return response()->json([
            'message' => 'Signhost request created',
            'sign_request' => new SignRequestResource($signRequest),
        ], 201);
    }

    public function resend(SignhostRequestActionRequest $request, ResendSignhostRequestAction $action)
    {
        $signRequest = $action->execute(
            $request->user(),
            $request->validated(),
            $request->header('Idempotency-Key') ?? $request->input('idempotency_key')
        );

        return response()->json([
            'message' => 'Signhost request resent',
            'sign_request' => new SignRequestResource($signRequest),
        ]);
    }

    public function cancel(SignhostRequestActionRequest $request, CancelSignhostRequestAction $action)
    {
        $signRequest = $action->execute(
            $request->user(),
            $request->validated(),
            $request->header('Idempotency-Key') ?? $request->input('idempotency_key')
        );

        return response()->json([
            'message' => 'Signhost request cancelled',
            'sign_request' => new SignRequestResource($signRequest),
        ]);
    }

    public function status(SignhostRequestQuery $request, GetSignhostStatusAction $action)
    {
        $signRequest = $action->execute($request->user(), $request->validated());

        return response()->json([
            'sign_request' => new SignRequestResource($signRequest),
        ]);
    }

    public function documents(SignhostRequestQuery $request, ListSignhostDocumentsAction $action, GetSignhostStatusAction $statusAction)
    {
        $documents = $action->execute($request->user(), $request->validated());

        $yacht = null;
        try {
            $signRequest = $statusAction->execute($request->user(), $request->validated());
            if ($signRequest->entity_type === 'Yacht') {
                $signRequest->load('yacht');
                $yr = $signRequest->yacht;
                $yacht = $yr ? [
                    'id' => $yr->id,
                    'name' => $yr->name,
                    'type' => $yr->type,
                    'year' => $yr->year,
                    'asking_price' => $yr->asking_price,
                ] : null;
            }
        } catch (\Throwable) {
        }

        return response()->json([
            'yacht' => $yacht,
            'documents' => SignDocumentResource::collection($documents),
        ]);
    }

    public function refreshSignUrl(Request $request, SignhostService $signhost): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'sign_request_id' => 'required_without:yacht_id|nullable|integer',
            'yacht_id' => 'required_without:sign_request_id|nullable|integer',
        ]);

        $query = SignRequest::query();
        if ($request->filled('sign_request_id')) {
            $signRequest = $query->findOrFail($request->integer('sign_request_id'));
        } else {
            $signRequest = $query
                ->where('entity_type', 'Yacht')
                ->where('entity_id', $request->integer('yacht_id'))
                ->latest()
                ->firstOrFail();
        }

        if (! $signRequest->signhost_transaction_id) {
            return response()->json(['message' => 'No Signhost transaction linked to this sign request.'], 422);
        }

        try {
            $transaction = $signhost->getTransaction($signRequest->signhost_transaction_id);
            $newUrl = $this->extractFirstSignerUrl($transaction);

            if ($newUrl) {
                $signRequest->update(['sign_url' => $newUrl]);
            }
        } catch (\Throwable $e) {
            Log::warning('SignhostController: refreshSignUrl failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'sign_request' => new SignRequestResource($signRequest->fresh()->load('documents')),
        ]);
    }

    private function extractFirstSignerUrl(array $transaction): ?string
    {
        $signers = $transaction['Signers'] ?? $transaction['signers'] ?? [];
        if (empty($signers)) {
            return null;
        }
        return $signers[0]['SignUrl'] ?? $signers[0]['signUrl'] ?? null;
    }

    // ── KYC helpers ──────────────────────────────────────────────

    private function abortIfKycBlocking(array $validated): void
    {
        $entityType = $validated['entity_type'] ?? null;
        $entityId   = $validated['entity_id'] ?? null;

        $blocking = match (strtolower((string) $entityType)) {
            'deal'  => KycCase::where('deal_id', $entityId)->where('blocking', true)->exists(),
            'yacht' => KycCase::where('yacht_id', $entityId)->where('blocking', true)->exists(),
            default => false,
        };

        if ($blocking) {
            abort(422, 'KYC-dossier blokkeert dit contract. Los de compliance problemen op voordat u verdergaat.');
        }
    }

    private function autoCreateKycFromContract(array $validated): void
    {
        try {
            $entityType = strtolower($validated['entity_type'] ?? '');
            $entityId   = $validated['entity_id'] ?? null;

            if ($entityType === 'deal' && $entityId) {
                $deal = Deal::find($entityId);
                if ($deal) {
                    app(CreateKycCaseAction::class)->fromDeal($deal);
                }
            } elseif ($entityType === 'yacht' && $entityId) {
                $yacht = Yacht::find($entityId);
                if ($yacht) {
                    app(CreateKycCaseAction::class)->fromYacht($yacht);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[SignhostController] KYC auto-create failed: ' . $e->getMessage());
        }
    }

    // Compatibility: NauticSecure yacht endpoints
    public function generateYachtContract(
        int $yachtId,
        SignhostGenerateContractRequest $request,
        GenerateContractAction $action,
        CreateSignhostRequestAction $createAction
    )
    {
        $validated = $request->validated();
        $payload = array_merge($validated, [
            'entity_type' => 'Yacht',
            'entity_id' => $yachtId,
        ]);

        // Blocking KYC check for this yacht
        $this->abortIfKycBlocking($payload);

        $signRequest = $action->execute($request->user(), $payload);
        $signRequest = $this->maybeCreateSignhost($request, $signRequest, $validated, $createAction);
        $metadata = $signRequest->metadata ?? [];

        // Auto-create KYC for yacht contract
        $this->autoCreateKycFromContract($payload);

        $response = [
            'message' => $signRequest->signhost_transaction_id ? 'Contract generated and Signhost request created' : 'Contract generated',
            'contract_pdf_path' => $metadata['contract_pdf_path'] ?? null,
            'contract_sha256' => $metadata['contract_sha256'] ?? null,
            'contract_pdf_paths' => $metadata['contract_pdf_paths'] ?? [],
            'contract_sha256s' => $metadata['contract_sha256s'] ?? [],
            'sign_url' => $signRequest->sign_url,
            'sign_request' => new SignRequestResource($signRequest),
        ];

        if ($signRequest->signhost_transaction_id) {
            $response['transaction'] = $this->legacyTransaction($signRequest);
        }

        return response()->json($response);
    }

    public function createYachtSignhost(int $yachtId, SignhostRequestCreateRequest $request, CreateSignhostRequestAction $action)
    {
        $payload = array_merge($request->validated(), [
            'entity_type' => 'Yacht',
            'entity_id' => $yachtId,
        ]);

        $signRequest = $action->execute(
            $request->user(),
            $payload,
            $request->header('Idempotency-Key') ?? $request->input('idempotency_key')
        );

        return response()->json([
            'message' => 'Signhost transaction created',
            'transaction' => $this->legacyTransaction($signRequest),
        ]);
    }

    public function yachtStatus(int $yachtId, Request $request, GetSignhostStatusAction $action)
    {
        $payload = [
            'entity_type' => 'Yacht',
            'entity_id' => $yachtId,
        ];

        $signRequest = $action->execute($request->user(), $payload);

        return response()->json([
            'transaction' => $this->legacyTransaction($signRequest),
        ]);
    }

    public function yachtDocuments(int $yachtId, Request $request, ListSignhostDocumentsAction $action)
    {
        $payload = [
            'entity_type' => 'Yacht',
            'entity_id' => $yachtId,
        ];

        $documents = $action->execute($request->user(), $payload);

        return response()->json([
            'documents' => SignDocumentResource::collection($documents),
        ]);
    }

    public function yachtSignUrl(int $yachtId, Request $request, GetSignhostStatusAction $action)
    {
        $payload = [
            'entity_type' => 'Yacht',
            'entity_id' => $yachtId,
        ];

        $signRequest = $action->execute($request->user(), $payload);
        $role = $request->query('role');
        $url = $this->resolveRoleUrl($signRequest, $role);

        return response()->json([
            'url' => $url,
        ]);
    }

    public function refreshYachtSignhostStatus(int $yachtId, Request $request, GetSignhostStatusAction $action, SignhostService $signhost): \Illuminate\Http\JsonResponse
    {
        $payload = [
            'entity_type' => 'Yacht',
            'entity_id' => $yachtId,
        ];

        try {
            $signRequest = $action->execute($request->user(), $payload);
        } catch (\Throwable) {
            return response()->json([
                'sign_request' => null,
                'signhost_status' => null,
            ]);
        }

        if (! $signRequest->signhost_transaction_id) {
            return response()->json([
                'sign_request' => new SignRequestResource($signRequest),
                'signhost_status' => null,
            ]);
        }

        try {
            $transaction = $signhost->getTransaction($signRequest->signhost_transaction_id);
        } catch (\Throwable $e) {
            Log::warning('SignhostController: refreshYachtSignhostStatus failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Signhost API error: ' . $e->getMessage()], 502);
        }

        $rawStatus = $transaction['Status'] ?? $transaction['status'] ?? null;
        $expiresOn = $transaction['ExpiresOn'] ?? $transaction['expiresOn'] ?? null;
        $signers   = $transaction['Signers'] ?? $transaction['signers'] ?? [];

        $buyerUrl  = $signers[0]['SignUrl'] ?? $signers[0]['signUrl'] ?? null;
        $sellerUrl = isset($signers[1]) ? ($signers[1]['SignUrl'] ?? $signers[1]['signUrl'] ?? null) : null;

        $signUrls = [];
        if ($buyerUrl)  { $signUrls[] = ['role' => 'buyer',  'url' => $buyerUrl]; }
        if ($sellerUrl) { $signUrls[] = ['role' => 'seller', 'url' => $sellerUrl]; }

        $internalStatus = match (strtoupper((string) $rawStatus)) {
            'WAITING_FOR_ALL', 'SIGNING', 'SENT', 'VIEWED' => 'SENT',
            'SIGNED' => 'SIGNED',
            'EXPIRED' => 'EXPIRED',
            'CANCELLED', 'REJECTED', 'DECLINED', 'FAILED' => 'FAILED',
            default => $signRequest->status,
        };

        $now = now();

        $metadata = array_merge($signRequest->metadata ?? [], [
            'signhost_raw_status' => $rawStatus,
            'sign_urls'           => $signUrls ?: ($signRequest->metadata['sign_urls'] ?? []),
        ]);

        $signRequest->update([
            'status'                   => $internalStatus,
            'metadata'                 => $metadata,
            // Dedicated columns
            'signhost_buyer_link'      => $buyerUrl ?? $signRequest->signhost_buyer_link,
            'signhost_seller_link'     => $sellerUrl ?? $signRequest->signhost_seller_link,
            'signhost_expires_at'      => $expiresOn ? $now->parse($expiresOn) : $signRequest->signhost_expires_at,
            'signhost_last_checked_at' => $now,
            'signhost_raw_response'    => $transaction,
        ]);

        // Audit log the manual status refresh
        app(\App\Services\ActionSecurity::class)->log(
            'signhost.status_checked',
            \App\Enums\RiskLevel::LOW,
            $request->user(),
            $signRequest,
            ['signhost_status' => $rawStatus],
            ['location_id' => $signRequest->location_id]
        );

        $signRequest = $signRequest->fresh()->load('documents');

        return response()->json([
            'sign_request'             => new SignRequestResource($signRequest),
            'signhost_status'          => $rawStatus,
            'signhost_expires_at'      => $expiresOn,
            'signhost_last_checked_at' => $now->toIso8601String(),
            'transaction'              => $this->legacyTransaction($signRequest),
        ]);
    }

    /**
     * Resync a Signhost transaction from the API without creating a new one.
     * Also used to retry failed webhook processing.
     */
    public function resyncYachtSignhost(int $yachtId, Request $request, GetSignhostStatusAction $action, SignhostService $signhost): \Illuminate\Http\JsonResponse
    {
        try {
            $signRequest = $action->execute($request->user(), [
                'entity_type' => 'Yacht',
                'entity_id'   => $yachtId,
            ]);
        } catch (\Throwable) {
            return response()->json(['error' => 'Sign request not found'], 404);
        }

        if (! $signRequest->signhost_transaction_id) {
            return response()->json(['error' => 'No Signhost transaction on this sign request'], 422);
        }

        try {
            $result = $signhost->resyncTransaction($signRequest->signhost_transaction_id);
        } catch (\Throwable $e) {
            Log::warning('SignhostController: resyncYachtSignhost failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Signhost API error: ' . $e->getMessage()], 502);
        }

        $transaction   = $result['transaction'];
        $rawStatus     = $result['status'];
        $internalStatus = match (strtoupper((string) $rawStatus)) {
            'WAITING_FOR_ALL', 'SIGNING', 'SENT', 'VIEWED' => 'SENT',
            'SIGNED'   => 'SIGNED',
            'EXPIRED'  => 'EXPIRED',
            'CANCELLED', 'REJECTED', 'DECLINED', 'FAILED' => 'FAILED',
            default    => $signRequest->status,
        };

        $signRequest->update([
            'status'                   => $internalStatus,
            'signhost_buyer_link'      => $result['buyer_url'] ?? $signRequest->signhost_buyer_link,
            'signhost_seller_link'     => $result['seller_url'] ?? $signRequest->signhost_seller_link,
            'signhost_expires_at'      => $result['expires_at'] ? now()->parse($result['expires_at']) : $signRequest->signhost_expires_at,
            'signhost_last_checked_at' => now(),
            'signhost_raw_response'    => $transaction,
            'webhook_failed'           => false,
            'webhook_error'            => null,
        ]);

        app(\App\Services\ActionSecurity::class)->log(
            'signhost.transaction_resync',
            \App\Enums\RiskLevel::MEDIUM,
            $request->user(),
            $signRequest->fresh(),
            ['signhost_status' => $rawStatus],
            ['location_id' => $signRequest->location_id]
        );

        return response()->json([
            'sign_request'   => new SignRequestResource($signRequest->fresh()->load('documents')),
            'signhost_status' => $rawStatus,
            'transaction'    => $this->legacyTransaction($signRequest->fresh()),
        ]);
    }

    private function legacyTransaction(SignRequest $signRequest): array
    {
        $buyerUrl  = $signRequest->signhost_buyer_link  ?? $this->resolveRoleUrl($signRequest, 'buyer');
        $sellerUrl = $signRequest->signhost_seller_link ?? $this->resolveRoleUrl($signRequest, 'seller');

        return [
            'id'                      => $signRequest->id,
            'yacht_id'                => $signRequest->entity_id,
            'signhost_transaction_id' => $signRequest->signhost_transaction_id,
            'status'                  => $this->mapLegacyStatus($signRequest->status),
            'signing_url_buyer'       => $buyerUrl,
            'signing_url_seller'      => $sellerUrl,
            'signed_pdf_path'         => $signRequest->signed_pdf_path ?? ($signRequest->metadata['signed_document_path'] ?? null),
            'signed_pdf_hash'         => $signRequest->signed_pdf_hash,
            'buyer_signed_at'         => $signRequest->buyer_signed_at?->toIso8601String(),
            'seller_signed_at'        => $signRequest->seller_signed_at?->toIso8601String(),
            'completed_at'            => $signRequest->completed_at?->toIso8601String(),
            'signhost_expires_at'     => $signRequest->signhost_expires_at?->toIso8601String(),
            'signhost_last_checked_at' => $signRequest->signhost_last_checked_at?->toIso8601String(),
            'webhook_failed'          => $signRequest->webhook_failed,
            'webhook_last_payload'    => $signRequest->webhook_last_payload ?? ($signRequest->metadata['webhook_last_payload'] ?? null),
            'created_at'              => $signRequest->created_at,
            'updated_at'              => $signRequest->updated_at,
        ];
    }

    private function mapLegacyStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'DRAFT', 'REQUESTED' => 'pending',
            'SENT', 'VIEWED' => 'signing',
            'SIGNED' => 'signed',
            'DECLINED' => 'rejected',
            'EXPIRED' => 'expired',
            'FAILED' => 'cancelled',
            default => 'signing',
        };
    }

    private function resolveRoleUrl(SignRequest $signRequest, ?string $role): ?string
    {
        $urls = $signRequest->metadata['sign_urls'] ?? [];
        $url = null;

        if ($role) {
            foreach ($urls as $entry) {
                if (($entry['role'] ?? null) === $role) {
                    $url = $entry['url'] ?? null;
                    break;
                }
            }
        }

        return $url ?: $signRequest->sign_url;
    }

    private function maybeCreateSignhost(
        Request $request,
        SignRequest $signRequest,
        array $validated,
        CreateSignhostRequestAction $action
    ): SignRequest {
        $recipients = $validated['recipients'] ?? [];
        $shouldCreate = $request->boolean('send_to_signhost') || $recipients !== [];

        if (! $shouldCreate) {
            return $signRequest->load('documents');
        }

        return $action->execute(
            $request->user(),
            array_filter([
                'sign_request_id' => $signRequest->id,
                'recipients' => $recipients,
                'reference' => $validated['reference'] ?? null,
                'password' => $validated['password'] ?? null,
                'otp_code' => $validated['otp_code'] ?? null,
            ], static fn ($value, $key) => $key === 'recipients' || $value !== null, ARRAY_FILTER_USE_BOTH),
            $request->header('Idempotency-Key') ?? $validated['idempotency_key'] ?? null
        );
    }
}
