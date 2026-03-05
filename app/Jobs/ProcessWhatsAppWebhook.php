<?php

namespace App\Jobs;

use App\Models\BlockedContact;
use App\Models\ChannelIdentity;
use App\Models\Conversation;
use App\Models\HarborChannel;
use App\Models\Message;
use App\Services\ChatConversationService;
use App\Services\WhatsApp360DialogService;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $harborChannelId,
        public array $payload
    ) {
    }

    public function handle(ChatConversationService $service, WhatsApp360DialogService $whatsApp): void
    {
        $channel = HarborChannel::find($this->harborChannelId);
        if (!$channel || !$channel->isActive()) {
            return;
        }

        $request = $this->fakeRequest();

        $inbound = $whatsApp->extractInboundMessages($this->payload);
        foreach ($inbound as $entry) {
            $this->handleInboundMessage($service, $channel, $entry, $request);
        }

        $statuses = $whatsApp->extractStatuses($this->payload);
        foreach ($statuses as $entry) {
            $this->handleStatus($entry);
        }
    }

    private function handleInboundMessage(ChatConversationService $service, HarborChannel $channel, array $entry, Request $request): void
    {
        $message = $entry['message'] ?? [];
        $metadata = $entry['metadata'] ?? [];
        $contacts = $entry['contacts'] ?? [];

        $waId = (string) ($message['from'] ?? ($contacts[0]['wa_id'] ?? ''));
        if ($waId === '') {
            return;
        }

        $externalMessageId = $message['id'] ?? null;
        if ($externalMessageId && Message::where('external_message_id', $externalMessageId)->exists()) {
            return;
        }

        $threadKey = $this->threadKey($channel->harbor_id, $waId);

        $identity = ChannelIdentity::where('type', 'whatsapp')
            ->where('external_thread_id', $threadKey)
            ->first();

        $conversation = $identity?->conversation;

        if (!$conversation) {
            $contactName = $contacts[0]['profile']['name'] ?? null;
            $conversation = $service->createConversation([
                'contact' => [
                    'name' => $contactName,
                    'whatsapp_user_id' => $waId,
                    'phone' => $waId,
                ],
                'channel_origin' => 'whatsapp',
                'harbor_id' => $channel->harbor_id,
                'language_preferred' => null,
                'reuse' => true,
                'allow_blocked_contacts' => true,
            ], $request);

            ChannelIdentity::updateOrCreate([
                'conversation_id' => $conversation->id,
                'type' => 'whatsapp',
                'external_thread_id' => $threadKey,
            ], [
                'external_user_id' => $waId,
                'metadata' => [
                    'display_phone_number' => $metadata['display_phone_number'] ?? null,
                    'phone_number_id' => $metadata['phone_number_id'] ?? null,
                ],
            ]);
        }

        $text = $this->extractText($message);
        $type = $message['type'] ?? 'text';

        $payload = [
            'sender_type' => 'visitor',
            'text' => $text,
            'language' => null,
            'channel' => 'whatsapp',
            'external_message_id' => $externalMessageId,
            'message_type' => $type,
            'metadata' => [
                'whatsapp' => [
                    'raw' => $message,
                ],
            ],
            'contact' => [
                'whatsapp_user_id' => $waId,
            ],
            'allow_blocked_contacts' => true,
        ];

        $saved = $service->addMessage($conversation, $payload, $request, null);

        if ($text && $this->isOptOut($text)) {
            $this->applyOptOut($conversation, $waId, $text);
        }

        if ($externalMessageId) {
            $saved->status = 'received';
            $saved->save();
        }
    }

    private function handleStatus(array $entry): void
    {
        $status = $entry['status'] ?? [];
        $externalId = $status['id'] ?? null;
        if (!$externalId) {
            return;
        }

        $message = Message::where('external_message_id', $externalId)->first();
        if (!$message) {
            return;
        }

        $state = $status['status'] ?? null;
        if (!$state) {
            return;
        }

        $message->status = $state;
        if ($state === 'delivered') {
            $message->delivered_at = now();
        }
        if ($state === 'read') {
            $message->read_at = now();
        }
        if ($state === 'failed') {
            $message->metadata = array_merge($message->metadata ?? [], [
                'whatsapp_error' => $status['errors'] ?? null,
            ]);
        }

        $message->save();
    }

    private function extractText(array $message): ?string
    {
        $type = $message['type'] ?? 'text';
        if ($type === 'text') {
            return $message['text']['body'] ?? null;
        }
        if ($type === 'button') {
            return $message['button']['text'] ?? null;
        }
        if ($type === 'interactive') {
            return $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? null;
        }

        return null;
    }

    private function threadKey(int $harborId, string $waId): string
    {
        return 'whatsapp:' . $harborId . ':' . $waId;
    }

    private function isOptOut(string $text): bool
    {
        $normalized = strtolower(trim($text));
        foreach (config('whatsapp.opt_out_phrases', []) as $phrase) {
            if ($phrase && str_contains($normalized, strtolower($phrase))) {
                return true;
            }
        }
        return false;
    }

    private function applyOptOut(Conversation $conversation, string $waId, string $text): void
    {
        $contact = $conversation->contact;
        if ($contact) {
            $contact->do_not_contact = true;
            $contact->consent_service_messages = false;
            $contact->save();
        }

        BlockedContact::updateOrCreate([
            'type' => 'whatsapp',
            'value' => $waId,
        ], [
            'reason' => 'opt_out',
            'blocked_until' => null,
        ]);

        $conversation->events()->create([
            'conversation_id' => $conversation->id,
            'type' => 'opt_out',
            'payload' => [
                'channel' => 'whatsapp',
                'text' => $text,
            ],
        ]);

        Log::info('WhatsApp opt-out applied', [
            'conversation_id' => $conversation->id,
            'wa_id' => $waId,
        ]);
    }

    private function fakeRequest(): Request
    {
        $request = Request::create('/webhooks/whatsapp/360dialog', 'POST', []);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        return $request;
    }
}
