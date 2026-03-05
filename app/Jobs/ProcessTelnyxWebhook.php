<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\PhoneCallService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTelnyxWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $eventId)
    {
    }

    public function handle(PhoneCallService $phoneService): void
    {
        $event = WebhookEvent::find($this->eventId);
        if (!$event) {
            return;
        }

        $payload = $event->payload_json ?? [];
        $eventType = data_get($payload, 'data.event_type') ?? data_get($payload, 'event_type');
        $eventPayload = data_get($payload, 'data.payload') ?? data_get($payload, 'payload') ?? $payload;
        $occurredAt = data_get($payload, 'data.occurred_at')
            ?? data_get($eventPayload, 'timestamp')
            ?? data_get($eventPayload, 'occurred_at');

        try {
            switch ($eventType) {
                case 'call.initiated':
                case 'call.incoming':
                case 'call.received':
                    $phoneService->handleCallInitiated($eventPayload, $occurredAt);
                    break;
                case 'call.answered':
                    $phoneService->handleCallAnswered($eventPayload, $occurredAt);
                    break;
                case 'call.hangup':
                case 'call.ended':
                case 'call.failed':
                    $phoneService->handleCallEnded($eventPayload, $occurredAt, $eventType);
                    break;
                case 'call.recording.saved':
                    $phoneService->handleRecordingSaved($eventPayload);
                    break;
                default:
                    Log::info('Telnyx webhook ignored', ['event_type' => $eventType]);
                    break;
            }

            $event->processed_at = now();
            $event->save();
        } catch (\Throwable $e) {
            Log::error('Telnyx webhook processing failed', [
                'event_id' => $event->id,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
