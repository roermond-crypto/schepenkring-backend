<?php

namespace App\Actions\Signhost;

use App\Enums\RiskLevel;
use App\Models\SignRequest;
use App\Models\User;
use App\Repositories\SignRequestRepository;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;
use App\Services\SignhostService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ResendSignhostRequestAction
{
    public function __construct(
        private SignRequestRepository $signRequests,
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
        $this->security->requireIdempotency($idempotencyKey, 'signhost.resend', $actor);

        $signRequest = $this->resolveRequest($actor, $data);
        $before = $signRequest->toArray();

        if (! $signRequest->signhost_transaction_id) {
            throw ValidationException::withMessages([
                'signhost_transaction_id' => 'No Signhost transaction available for resend.',
            ]);
        }

        $this->signhost->resendTransaction($signRequest->signhost_transaction_id);

        $metadata = array_merge($signRequest->metadata ?? [], [
            'resent_at' => now()->toDateTimeString(),
        ]);

        $signRequest = $this->signRequests->update($signRequest, [
            'status' => 'SENT',
            'metadata' => $metadata,
        ]);

        $this->security->log('signhost.resend', RiskLevel::HIGH, $actor, $signRequest, [], [
            'location_id' => $signRequest->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $signRequest->toArray(),
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->notifyRecipients($signRequest, 'Signing request resent', 'A signing request has been resent.');

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
                throw ValidationException::withMessages([
                    'sign_request' => 'Sign request not found.',
                ]);
            }
        }

        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        return $signRequest;
    }

    private function notifyRecipients(SignRequest $signRequest, string $title, string $message): void
    {
        $recipientIds = $this->recipientUserIds($signRequest);
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
                    'url' => "/dashboard/admin/contracts/{$signRequest->id}",
                ],
                null,
                true,
                true,
                $signRequest->location_id
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function recipientUserIds(SignRequest $signRequest): array
    {
        $ids = [];
        if ($signRequest->requested_by_user_id) {
            $ids[] = $signRequest->requested_by_user_id;
        }

        $recipients = $signRequest->metadata['recipients'] ?? [];
        foreach ($recipients as $recipient) {
            if (! empty($recipient['user_id'])) {
                $ids[] = (int) $recipient['user_id'];
            }
        }

        return array_values(array_unique($ids));
    }
}
