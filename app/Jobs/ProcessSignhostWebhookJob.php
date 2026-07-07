<?php

namespace App\Jobs;

use App\Enums\AuditResult;
use App\Enums\RiskLevel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SignDocument;
use App\Models\SignRequest;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;
use App\Services\SignhostService;
use App\Support\SignhostRecipientSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessSignhostWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $eventId)
    {
    }

    public function handle(SignhostService $signhost, ActionSecurity $security, NotificationDispatchService $notifications): void
    {
        $event = WebhookEvent::find($this->eventId);
        if (! $event || $event->processed_at) {
            return;
        }

        $payload = $event->payload_json ?? [];
        $transactionId = $payload['TransactionId'] ?? $payload['transactionId'] ?? null;
        $status = $payload['Status'] ?? $payload['status'] ?? null;

        if (! $transactionId || ! $status) {
            $this->markProcessed($event);
            return;
        }

        $signRequest = SignRequest::where('signhost_transaction_id', $transactionId)->first();
        if (! $signRequest) {
            $this->markProcessed($event);
            return;
        }

        $previous = $signRequest->toArray();
        $mapped = $this->mapSignhostStatus((string) $status);

        $metadata = array_merge($signRequest->metadata ?? [], [
            'webhook_status'      => $status,
            'webhook_received_at' => now()->toDateTimeString(),
        ]);

        // Update dedicated columns from webhook payload
        $updates = [
            'status'               => $mapped,
            'metadata'             => $metadata,
            'webhook_last_payload' => $payload,
            'webhook_failed'       => false,
            'webhook_error'        => null,
            'signhost_last_checked_at' => now(),
        ];

        // Extract per-participant signing timestamps from the payload
        $signers = $payload['Signers'] ?? $payload['signers'] ?? [];
        foreach ($signers as $index => $signer) {
            $signedAt = $signer['SignedDateTime'] ?? $signer['signedDateTime'] ?? null;
            if ($signedAt) {
                if ($index === 0 && !$signRequest->buyer_signed_at) {
                    $updates['buyer_signed_at'] = $signedAt;
                } elseif ($index === 1 && !$signRequest->seller_signed_at) {
                    $updates['seller_signed_at'] = $signedAt;
                } elseif ($index === 2 && !$signRequest->broker_signed_at) {
                    $updates['broker_signed_at'] = $signedAt;
                }
            }
        }

        if ($mapped === 'SIGNED' && !$signRequest->completed_at) {
            $updates['completed_at'] = now();
        }

        $signRequest->fill($updates)->save();

        if ($mapped === 'SIGNED') {
            $this->storeSignedDocuments($signhost, $signRequest);
        }

        $security->log('signhost.status.updated', RiskLevel::MEDIUM, null, $signRequest, [
            'status' => $mapped,
        ], [
            'location_id' => $signRequest->location_id,
            'snapshot_before' => $previous,
            'snapshot_after' => $signRequest->toArray(),
            'result' => AuditResult::SUCCESS->value,
        ]);

        $this->notifyStatusChange($notifications, $signRequest, $mapped);
        $this->postChatSystemMessage($signRequest, $mapped, $payload);

        $this->markProcessed($event);
    }

    private function storeSignedDocuments(SignhostService $signhost, SignRequest $signRequest): void
    {
        $signed = $signhost->downloadSignedFile($signRequest->signhost_transaction_id ?? '');
        if (! $signed) {
            return;
        }

        $fileName = Str::slug($signRequest->entity_type)."_{$signRequest->entity_id}_signed_".now()->format('Ymd_His').'.pdf';
        $path = "contracts/{$fileName}";

        Storage::disk('public')->put($path, $signed);
        $sha256 = hash('sha256', $signed);

        SignDocument::create([
            'sign_request_id' => $signRequest->id,
            'file_path' => $path,
            'sha256' => $sha256,
            'type' => 'signed',
        ]);

        $metadata = $signRequest->metadata ?? [];
        $metadata['signed_at'] = now()->toDateTimeString();
        $signRequest->metadata         = $metadata;
        $signRequest->signed_pdf_path  = $path;
        $signRequest->signed_pdf_hash  = $sha256;
        $signRequest->save();
    }

    private function notifyStatusChange(NotificationDispatchService $notifications, SignRequest $signRequest, string $status): void
    {
        $title = match ($status) {
            'SENT' => 'Signing request sent',
            'VIEWED' => 'Signing request viewed',
            'SIGNED' => 'Contract signed',
            'DECLINED' => 'Contract declined',
            'EXPIRED' => 'Contract expired',
            'FAILED' => 'Signing failed',
            default => 'Signing status updated',
        };

        $message = "Signing status updated to {$status}.";

        $recipientIds = SignhostRecipientSupport::recipientUserIds($signRequest);
        foreach ($recipientIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }
            $notifications->notifyUser(
                $user,
                'info',
                $title,
                $message,
                [
                    'entity_type' => $signRequest->entity_type,
                    'entity_id' => $signRequest->entity_id,
                    'sign_request_id' => $signRequest->id,
                    'status' => $status,
                    'url' => SignhostRecipientSupport::notificationUrl($signRequest),
                ],
                null,
                true,
                true,
                $signRequest->location_id
            );
        }

        if ($status === 'SIGNED') {
            foreach ($recipientIds as $userId) {
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }
                $notifications->notifyUser(
                    $user,
                    'success',
                    'Signed documents available',
                    'Signed documents are ready for download.',
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

    /**
     * Post a system message to the yacht's chat conversation so the Chat Hub
     * "signhost" filter tab shows live Signhost events.
     */
    private function postChatSystemMessage(SignRequest $signRequest, string $status, array $payload): void
    {
        if ($signRequest->entity_type !== 'Yacht') {
            return;
        }

        $yachtId = $signRequest->entity_id;

        // Find an existing conversation linked to this yacht
        $conversation = Conversation::where('boat_id', $yachtId)
            ->whereIn('channel', ['contract', 'owner_bid', 'sales', 'chat'])
            ->latest('last_message_at')
            ->first();

        if (!$conversation) {
            return;
        }

        $text = match ($status) {
            'SENT'     => '📄 Signhost ondertekeningsverzoek verzonden naar koper en verkoper.',
            'VIEWED'   => '👀 Signhost document bekeken door een ondertekenaar.',
            'SIGNED'   => '✅ Contract volledig ondertekend via Signhost.',
            'DECLINED' => '❌ Contract geweigerd door een ondertekenaar.',
            'EXPIRED'  => '⏰ Signhost transactie verlopen. Maak een nieuwe aan.',
            'FAILED'   => '⚠️ Signhost transactie mislukt.',
            default    => "🔔 Signhost status bijgewerkt naar: {$status}",
        };

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => 'system',
            'text'            => $text,
            'body'            => $text,
            'channel'         => 'contract',
            'message_type'    => 'system',
            'metadata'        => [
                'event'              => 'signhost_status_updated',
                'signhost_status'    => $status,
                'sign_request_id'    => $signRequest->id,
                'transaction_id'     => $signRequest->signhost_transaction_id,
                'entity_type'        => $signRequest->entity_type,
                'entity_id'          => $signRequest->entity_id,
            ],
            'delivery_state'  => 'sent',
            'role_visibility' => ['admin', 'broker', 'seller'],
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();
    }

    private function mapSignhostStatus(string $status): string
    {
        $status = strtolower($status);

        return match ($status) {
            'draft' => 'DRAFT',
            'requested' => 'REQUESTED',
            'sent' => 'SENT',
            'viewed' => 'VIEWED',
            'signed' => 'SIGNED',
            'rejected', 'declined' => 'DECLINED',
            'expired' => 'EXPIRED',
            'failed', 'cancelled' => 'FAILED',
            default => 'SENT',
        };
    }

    private function markProcessed(WebhookEvent $event): void
    {
        $event->update([
            'processed_at' => now(),
            'processing_at' => null,
        ]);
    }
}
