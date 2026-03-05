<?php

namespace App\Jobs;

use App\Models\ConversationEvent;
use App\Models\HarborChannel;
use App\Models\Message;
use App\Models\TemplateSendLog;
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
        if (!$message || $message->channel !== 'whatsapp' || $message->sender_type === 'visitor') {
            return;
        }

        $conversation = $message->conversation;
        if (!$conversation) {
            return;
        }

        $contact = $conversation->contact;
        $waId = $contact?->whatsapp_user_id;
        if (!$waId) {
            $this->markFailed($message, 'missing_recipient');
            return;
        }

        if ($contact?->do_not_contact) {
            $this->markFailed($message, 'do_not_contact');
            return;
        }

        $channel = HarborChannel::where('harbor_id', $conversation->harbor_id)
            ->where('channel', 'whatsapp')
            ->where('provider', '360dialog')
            ->where('status', 'active')
            ->first();

        if (!$channel) {
            $this->markFailed($message, 'missing_channel');
            return;
        }

        $limit = (int) config('whatsapp.outbound_rate_limit_per_minute', 60);
        $rateKey = 'whatsapp:outbound:' . $conversation->harbor_id;
        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            $this->release(30);
            return;
        }
        RateLimiter::hit($rateKey, 60);

        $windowExpires = $conversation->window_expires_at;
        if (!$windowExpires && $conversation->last_inbound_at) {
            $windowExpires = $conversation->last_inbound_at->copy()->addHours(24);
        }

        $withinWindow = $windowExpires ? now()->lessThanOrEqualTo($windowExpires) : false;

        $template = $message->metadata['whatsapp']['template'] ?? null;

        if (!$withinWindow && !$template) {
            $this->markFailed($message, 'template_required');
            $this->recordEvent($conversation->id, 'whatsapp_blocked', [
                'message_id' => $message->id,
                'reason' => 'template_required',
            ]);
            return;
        }

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
            $messageId = $response['messages'][0]['id'] ?? null;
            if ($messageId) {
                $message->external_message_id = $messageId;
            }
            $message->status = 'sent';
            $message->metadata = array_merge($message->metadata ?? [], [
                'whatsapp' => [
                    'response' => $response,
                ],
            ]);
            $message->save();

            if ($template) {
                TemplateSendLog::create([
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'harbor_id' => $conversation->harbor_id,
                    'template_name' => $payload['template']['name'] ?? 'unknown',
                    'language' => $payload['template']['language']['code'] ?? null,
                    'params' => $template['components'] ?? null,
                    'reason' => $template['reason'] ?? null,
                    'status' => 'sent',
                ]);
            }

            $this->recordEvent($conversation->id, 'whatsapp_sent', [
                'message_id' => $message->id,
                'external_message_id' => $message->external_message_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed', ['error' => $e->getMessage()]);
            $this->markFailed($message, 'send_failed');
        }
    }

    private function markFailed(Message $message, string $reason): void
    {
        $message->status = 'failed';
        $message->metadata = array_merge($message->metadata ?? [], [
            'whatsapp_error' => $reason,
        ]);
        $message->save();
    }

    private function recordEvent(string $conversationId, string $type, array $payload): void
    {
        ConversationEvent::create([
            'conversation_id' => $conversationId,
            'type' => $type,
            'payload' => $payload,
        ]);
    }
}
