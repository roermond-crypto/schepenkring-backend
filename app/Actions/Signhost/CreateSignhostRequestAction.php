<?php

namespace App\Actions\Signhost;

use App\Enums\RiskLevel;
use App\Models\SignRequest;
use App\Models\User;
use App\Repositories\SignRequestRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\NotificationDispatchService;
use App\Services\SignhostService;
use App\Support\SignhostRecipientSupport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSignhostRequestAction
{
    public function __construct(
        private SignRequestRepository $signRequests,
        private LocationAccessService $locationAccess,
        private ActionSecurity $security,
        private SignhostService $signhost,
        private NotificationDispatchService $notifications
    ) {
    }

    public function execute(User $actor, array $data, ?string $idempotencyKey): SignRequest
    {
        if (!empty($data['password'])) {
            $this->security->assertFreshAuth($actor, $data['password'], $data['otp_code'] ?? null);
        }
        $this->security->requireIdempotency($idempotencyKey, 'signhost.request', $actor);

        $signRequest = $this->resolveRequest($actor, $data);
        $before = $signRequest->toArray();

        // Prevent creating a duplicate active Signhost transaction.
        // If an active (non-expired, non-failed) transaction already exists,
        // the caller must explicitly pass force_new=true to override.
        if ($signRequest->signhost_transaction_id && empty($data['force_new'])) {
            $activeStatuses = ['DRAFT', 'REQUESTED', 'SENT', 'VIEWED'];
            if (in_array($signRequest->status, $activeStatuses, true)) {
                throw ValidationException::withMessages([
                    'signhost_transaction_id' => 'An active Signhost transaction already exists for this contract. Pass force_new=true to create a new one.',
                ]);
            }
        }

        $documents = $signRequest->documents()->where('type', 'original')->latest()->get();
        if ($documents->isEmpty()) {
            throw ValidationException::withMessages([
                'document' => 'No contract document available. Generate the contract first.',
            ]);
        }

        $pdfPaths = $documents->map(fn ($doc) => Storage::disk('public')->path($doc->file_path))->values()->all();

        $recipients = $this->normalizeRecipients($data['recipients']);
        if (count($recipients) === 0) {
            throw ValidationException::withMessages([
                'recipients' => 'At least one recipient is required.',
            ]);
        }

        $reference = $data['reference'] ?? Str::slug($signRequest->entity_type).'-'.$signRequest->entity_id;
        $result = $this->signhost->createTransaction($recipients, $pdfPaths, $reference);

        $transaction = $result['transaction'] ?? [];
        $signUrls = $this->extractSigningUrls($transaction, $recipients);

        $buyerUrl  = $signUrls[0]['url'] ?? null;
        $sellerUrl = $signUrls[1]['url'] ?? null;
        $expiresAt = $transaction['ExpiresOn'] ?? $transaction['expiresOn'] ?? null;

        $metadata = array_merge($signRequest->metadata ?? [], [
            'recipients'  => $data['recipients'],
            'sign_urls'   => $signUrls,
            'reference'   => $reference,
            'sent_at'     => now()->toDateTimeString(),
        ]);

        $signRequest = $this->signRequests->update($signRequest, [
            'provider'                  => 'signhost',
            'status'                    => 'SENT',
            'signhost_transaction_id'   => $result['transaction_id'],
            'sign_url'                  => $buyerUrl,
            'requested_by_user_id'      => $actor->id,
            'metadata'                  => $metadata,
            // Dedicated columns
            'signhost_buyer_link'       => $buyerUrl,
            'signhost_seller_link'      => $sellerUrl,
            'signhost_created_at'       => now(),
            'signhost_expires_at'       => $expiresAt ? now()->parse($expiresAt) : null,
            'signhost_last_checked_at'  => now(),
            'signhost_raw_response'     => $transaction,
        ]);

        $this->security->log('signhost.request', RiskLevel::HIGH, $actor, $signRequest, [
            'entity_type' => $signRequest->entity_type,
            'entity_id' => $signRequest->entity_id,
        ], [
            'location_id' => $signRequest->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $signRequest->toArray(),
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->notifyRecipients($signRequest, 'Signing request sent', 'A signing request is ready.');

        return $signRequest->load('documents');
    }

    private function resolveRequest(User $actor, array $data): SignRequest
    {
        if (! empty($data['sign_request_id'])) {
            $signRequest = $this->signRequests->findForUserOrFail($actor, (int) $data['sign_request_id']);
        } else {
            if (empty($data['entity_type']) || empty($data['entity_id'])) {
                throw ValidationException::withMessages([
                    'entity' => 'entity_type and entity_id are required when sign_request_id is missing.',
                ]);
            }

            $signRequest = $this->signRequests->findLatestForEntity(
                $actor,
                $data['entity_type'],
                (int) $data['entity_id']
            );

            if (! $signRequest) {
                if (empty($data['location_id'])) {
                    throw ValidationException::withMessages([
                        'location_id' => 'location_id is required when creating a new signing request.',
                    ]);
                }

                $locationId = (int) $data['location_id'];
                if (! $actor->isAdmin() && ! $this->locationAccess->sharesLocation($actor, $locationId)) {
                    throw new AuthorizationException('Unauthorized');
                }

                $signRequest = $this->signRequests->create([
                    'location_id' => $locationId,
                    'entity_type' => $data['entity_type'],
                    'entity_id' => (int) $data['entity_id'],
                    'provider' => 'signhost',
                    'status' => 'DRAFT',
                    'metadata' => [],
                ]);
            }
        }

        if (! $actor->isAdmin() && $signRequest->location_id) {
            if (! $this->locationAccess->sharesLocation($actor, $signRequest->location_id)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        return $signRequest;
    }

    /**
     * @return array<int, array{email:string,name:string,send?:bool,role?:string,user_id?:int}>
     */
    private function normalizeRecipients(array $recipients): array
    {
        $result = [];

        foreach ($recipients as $recipient) {
            $email = $recipient['email'] ?? null;
            $name = $recipient['name'] ?? null;

            if (! empty($recipient['user_id'])) {
                $user = User::find($recipient['user_id']);
                if ($user) {
                    $email = $user->email;
                    $name = $user->name;
                }
            }

            if (! $email || ! $name) {
                continue;
            }

            $result[] = [
                'email' => $email,
                'name' => $name,
                'send' => $recipient['send'] ?? true,
                'role' => $recipient['role'] ?? null,
                'user_id' => $recipient['user_id'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array{email:string,name:string,role?:string}> $recipients
     * @return array<int, array{role:string|null,url:string|null}>
     */
    private function extractSigningUrls(array $transaction, array $recipients): array
    {
        $signers = $transaction['Signers'] ?? $transaction['signers'] ?? [];
        $urls = [];

        foreach ($recipients as $index => $recipient) {
            $url = $signers[$index]['SignUrl'] ?? $signers[$index]['signUrl'] ?? null;
            $urls[] = [
                'role' => $recipient['role'] ?? null,
                'url' => $url,
            ];
        }

        return $urls;
    }

    private function notifyRecipients(SignRequest $signRequest, string $title, string $message): void
    {
        $recipientIds = SignhostRecipientSupport::recipientUserIds($signRequest);
        foreach ($recipientIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }
            $this->notifications->notifyUser(
                $user,
                'info',
                $title,
                $message,
                [
                    'entity_type' => $signRequest->entity_type,
                    'entity_id' => $signRequest->entity_id,
                    'sign_request_id' => $signRequest->id,
                    'url' => SignhostRecipientSupport::notificationUrl($signRequest),
                ],
                null,
                true,
                true,
                $signRequest->location_id
            );
        }
    }
}
