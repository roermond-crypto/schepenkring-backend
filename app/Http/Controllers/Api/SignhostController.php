<?php

namespace App\Http\Controllers\Api;

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
use App\Http\Resources\SignDocumentResource;
use App\Http\Resources\SignRequestResource;
use App\Models\SignRequest;
use Illuminate\Http\Request;

class SignhostController extends Controller
{
    public function generateContract(
        SignhostGenerateContractRequest $request,
        GenerateContractAction $action,
        CreateSignhostRequestAction $createAction
    )
    {
        $validated = $request->validated();
        $signRequest = $action->execute($request->user(), $validated);
        $signRequest = $this->maybeCreateSignhost($request, $signRequest, $validated, $createAction);
        $metadata = $signRequest->metadata ?? [];

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

    public function documents(SignhostRequestQuery $request, ListSignhostDocumentsAction $action)
    {
        $documents = $action->execute($request->user(), $request->validated());

        return response()->json([
            'documents' => SignDocumentResource::collection($documents),
        ]);
    }

    // Compatibility: NauticSecure deal endpoints
    public function generateDealContract(
        int $dealId,
        SignhostGenerateContractRequest $request,
        GenerateContractAction $action,
        CreateSignhostRequestAction $createAction
    )
    {
        $validated = $request->validated();
        $payload = array_merge($validated, [
            'entity_type' => 'Deal',
            'entity_id' => $dealId,
        ]);

        $signRequest = $action->execute($request->user(), $payload);
        $signRequest = $this->maybeCreateSignhost($request, $signRequest, $validated, $createAction);
        $metadata = $signRequest->metadata ?? [];

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

    public function createDealSignhost(int $dealId, SignhostRequestCreateRequest $request, CreateSignhostRequestAction $action)
    {
        $payload = array_merge($request->validated(), [
            'entity_type' => 'Deal',
            'entity_id' => $dealId,
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

    public function dealStatus(int $dealId, Request $request, GetSignhostStatusAction $action)
    {
        $payload = [
            'entity_type' => 'Deal',
            'entity_id' => $dealId,
        ];

        $signRequest = $action->execute($request->user(), $payload);

        return response()->json([
            'transaction' => $this->legacyTransaction($signRequest),
        ]);
    }

    public function dealDocuments(int $dealId, Request $request, ListSignhostDocumentsAction $action)
    {
        $payload = [
            'entity_type' => 'Deal',
            'entity_id' => $dealId,
        ];

        $documents = $action->execute($request->user(), $payload);

        return response()->json([
            'documents' => SignDocumentResource::collection($documents),
        ]);
    }

    public function dealSignUrl(int $dealId, Request $request, GetSignhostStatusAction $action)
    {
        $payload = [
            'entity_type' => 'Deal',
            'entity_id' => $dealId,
        ];

        $signRequest = $action->execute($request->user(), $payload);
        $role = $request->query('role');
        $url = $this->resolveRoleUrl($signRequest, $role);

        return response()->json([
            'url' => $url,
        ]);
    }

    private function legacyTransaction(SignRequest $signRequest): array
    {
        $metadata = $signRequest->metadata ?? [];
        $buyerUrl = $this->resolveRoleUrl($signRequest, 'buyer');
        $sellerUrl = $this->resolveRoleUrl($signRequest, 'seller');

        return [
            'id' => $signRequest->id,
            'deal_id' => $signRequest->entity_id,
            'signhost_transaction_id' => $signRequest->signhost_transaction_id,
            'status' => $this->mapLegacyStatus($signRequest->status),
            'signing_url_buyer' => $buyerUrl,
            'signing_url_seller' => $sellerUrl,
            'signed_pdf_path' => $metadata['signed_document_path'] ?? null,
            'webhook_last_payload' => $metadata['webhook_last_payload'] ?? null,
            'created_at' => $signRequest->created_at,
            'updated_at' => $signRequest->updated_at,
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
