<?php

namespace App\Jobs;

use App\Models\ChannelIdentity;
use App\Models\HarborChannel;
use App\Models\Message;
use App\Services\WhatsApp360DialogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $messageId)
    {
    }

    public function handle(WhatsApp360DialogService $service): void
    {
        $message = Message::with('conversation.contact')->find($this->messageId);
        if (! $message || $message->channel !== 'whatsapp' || $message->sender_type === 'visitor') {
            return;
        }

        $conversation = $message->conversation;
        if (! $conversation) {
            return;
        }

        $contact = $conversation->contact;
        $waId = $contact?->whatsapp_user_id;
        if (! $waId) {
            $this->markFailed($message, 'missing_recipient');

            return;
        }

        if ($contact?->do_not_contact) {
            $this->markFailed($message, 'do_not_contact');

            return;
        }

        $channel = HarborChannel::where('harbor_id', $conversation->location_id)
            ->where('channel', 'whatsapp')
            ->where('provider', '360dialog')
            ->where('status', 'active')
            ->first();

        if (! $channel) {
            $this->markFailed($message, 'missing_channel');

            return;
        }

        $limit = (int) config('whatsapp.outbound_rate_limit_per_minute', 60);
        $rateKey = 'whatsapp:outbound:'.$conversation->location_id;
        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            $this->release(30);

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $windowExpires = $conversation->window_expires_at;
        if (! $windowExpires && $conversation->last_inbound_at) {
            $windowExpires = $conversation->last_inbound_at->copy()->addHours(24);
        }

        $withinWindow = $windowExpires ? now()->lessThanOrEqualTo($windowExpires) : false;
        $template = $message->metadata['whatsapp']['template'] ?? null;

        if (! $withinWindow && ! $template) {
            $this->markFailed($message, 'template_required');

            return;
        }

        ChannelIdentity::updateOrCreate([
            'conversation_id' => $conversation->id,
            'type' => 'whatsapp',
            'external_thread_id' => $this->threadKey((int) $conversation->location_id, (string) $waId),
        ], [
            'external_user_id' => $waId,
            'metadata' => array_filter([
                'display_phone_number' => $channel->from_number,
                'phone_number_id' => $channel->metadata['phone_number_id'] ?? null,
                'provider' => '360dialog',
                'sandbox' => (bool) ($channel->metadata['sandbox'] ?? false),
            ], static fn ($value) => $value !== null),
        ]);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $waId,
        ];

        if ($template) {
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $template['name'] ?? $template['template_name'] ?? null,
                'language' => [
                    'code' => $template['language'] ?? 'en_US',
                ],
                'components' => $template['components'] ?? [],
            ];
        } else {
            $payload['type'] = 'text';
            $payload['text'] = [
                'body' => (string) $message->text,
            ];
        }

        try {
            $response = $service->sendMessage($channel, $payload);
            $externalId = $response['messages'][0]['id'] ?? null;
            if ($externalId) {
                $message->external_message_id = $externalId;
            }

            $message->status = 'sent';
            $message->delivery_state = 'sent';
            $message->metadata = array_merge($message->metadata ?? [], [
                'whatsapp' => array_merge($message->metadata['whatsapp'] ?? [], [
                    'request' => $payload,
                    'response' => $response,
                    'recipient_id' => $waId,
                    'harbor_channel_id' => $channel->id,
                ]),
            ]);
            $message->save();
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed', ['error' => $e->getMessage()]);
            $this->markFailed($message, 'send_failed');
        }
    }

    private function markFailed(Message $message, string $reason): void
    {
        $message->status = 'failed';
        $message->delivery_state = 'failed';
        $message->metadata = array_merge($message->metadata ?? [], [
            'whatsapp_error' => $reason,
        ]);
        $message->save();
    }

    private function threadKey(int $harborId, string $waId): string
    {
        return 'whatsapp:'.$harborId.':'.$waId;
    }
}
