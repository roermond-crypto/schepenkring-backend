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

class ProcessRetellWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $eventId)
    {
    }

    public function handle(PhoneCallService $phoneService): void
    {
        $event = WebhookEvent::find($this->eventId);
        if (! $event) {
            return;
        }

        $payload = $event->payload_json ?? [];
        $eventType = $payload['event'] ?? null;
        $call = is_array($payload['call'] ?? null) ? $payload['call'] : [];

        try {
            switch ($eventType) {
                case 'call_started':
                    $phoneService->handleRetellCallStarted($call);
                    break;
                case 'call_ended':
                    $phoneService->handleRetellCallEnded($call);
                    break;
                case 'call_analyzed':
                    $phoneService->handleRetellCallAnalyzed($call);
                    break;
                default:
                    // Retell's documented webhook taxonomy is only these
                    // three events — call failures surface as a
                    // call_status/disconnection_reason on call_ended,
                    // handled there, not as a separate error event. Still,
                    // an unrecognized event type is recorded (not just
                    // logged and discarded) in case Retell's API adds one
                    // later that this integration doesn't know about yet.
                    $phoneService->handleRetellUnknownEvent($eventType, $call, $payload);
                    Log::warning('Retell webhook event type not recognized', ['event_type' => $eventType]);
                    break;
            }

            $event->processed_at = now();
            $event->save();
        } catch (\Throwable $e) {
            Log::error('Retell webhook processing failed', [
                'event_id' => $event->id,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
