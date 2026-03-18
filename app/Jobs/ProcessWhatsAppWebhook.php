<?php

namespace App\Jobs;

use App\Models\BlockedContact;
use App\Models\ChannelIdentity;
use App\Models\HarborChannel;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatAiReplyService;
use App\Services\ChatConversationService;
use App\Services\PhoneNumberService;
use App\Services\WhatsApp360DialogService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
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

    public function handle(
        ChatConversationService $service,
        WhatsApp360DialogService $whatsApp,
        ChatAiReplyService $ai,
        PhoneNumberService $phoneService
    ): void {
        $channel = HarborChannel::find($this->harborChannelId);
        if (! $channel || ! $channel->isActive()) {
            return;
        }

        $request = $this->fakeRequest();

        foreach ($whatsApp->extractInboundMessages($this->payload) as $entry) {
            $this->handleInboundMessage($service, $ai, $channel, $entry, $request, $phoneService);
        }

        foreach ($whatsApp->extractStatuses($this->payload) as $entry) {
            $this->handleStatus($entry);
        }
    }

    private function handleInboundMessage(
        ChatConversationService $service,
        ChatAiReplyService $ai,
        HarborChannel $channel,
        array $entry,
        Request $request,
        PhoneNumberService $phoneService
    ): void {
        $message  = $entry['message']  ?? [];
        $metadata = $entry['metadata'] ?? [];
        $contacts = $entry['contacts'] ?? [];

        // Raw WhatsApp ID (e.g. "31612345678" — no leading +)
        $rawWaId = (string) ($message['from'] ?? ($contacts[0]['wa_id'] ?? ''));
        if ($rawWaId === '') {
            return;
        }

        // ── 1. Normalize to E.164 (+31612345678) ──────────────────────────────
        // WhatsApp always sends numbers without the leading "+", so we prepend it
        // before running through PhoneNumberService so that matching against
        // users.phone (which is stored in E.164) works correctly.
        $normalizedPhone = $phoneService->normalize(
            str_starts_with($rawWaId, '+') ? $rawWaId : '+' . $rawWaId
        ) ?? $rawWaId;

        // Deduplicate: skip if we already processed this external message.
        $externalMessageId = $message['id'] ?? null;
        if ($externalMessageId && Message::where('external_message_id', $externalMessageId)->exists()) {
            return;
        }

        // ── 2. Find or create conversation ────────────────────────────────────
        $threadKey = $this->threadKey($channel->harbor_id, $rawWaId);
        $identity  = ChannelIdentity::where('type', 'whatsapp')
            ->where('external_thread_id', $threadKey)
            ->first();

        $conversation = $identity?->conversation;

        // Try to resume via a quoted/replied-to message.
        $contextMessageId = $message['context']['id'] ?? null;
        if (! $conversation && $contextMessageId) {
            $conversation = Message::where('external_message_id', $contextMessageId)
                ->with('conversation')
                ->first()
                ?->conversation;
        }

        if (! $conversation) {
            $contactName = $contacts[0]['profile']['name'] ?? null;
            $conversation = $service->createConversation([
                'contact' => [
                    'name'             => $contactName,
                    'whatsapp_user_id' => $rawWaId,
                    // Store the normalized phone so it matches users.phone
                    'phone'            => $normalizedPhone,
                ],
                'channel_origin'          => 'whatsapp',
                'harbor_id'               => $channel->harbor_id,
                'language_preferred'      => null,
                'reuse'                   => true,
                'allow_blocked_contacts'  => true,
            ], $request);
        }

        // ── 3. Link conversation to a registered User by phone ────────────────
        // If the conversation is not yet linked to a user, try to find one by
        // the normalized phone number so the thread appears in their dashboard.
        if (! $conversation->user_id) {
            $linkedUser = $this->resolveUserByPhone($normalizedPhone, $rawWaId);
            if ($linkedUser) {
                $conversation->user_id = $linkedUser->id;
                // Also ensure the conversation is scoped to the user's location
                // when no location was set yet.
                if (! $conversation->location_id && $linkedUser->client_location_id) {
                    $conversation->location_id = $linkedUser->client_location_id;
                }
                $conversation->save();

                // Keep the contact in sync with the user record.
                if ($conversation->contact && ! $conversation->contact->user_id) {
                    $conversation->contact->user_id = $linkedUser->id;
                    $conversation->contact->save();
                }

                Log::info('WhatsApp conversation linked to user', [
                    'conversation_id' => $conversation->id,
                    'user_id'         => $linkedUser->id,
                    'phone'           => $normalizedPhone,
                ]);
            }
        }

        // ── 4. Upsert channel identity ─────────────────────────────────────────
        ChannelIdentity::updateOrCreate([
            'conversation_id'   => $conversation->id,
            'type'              => 'whatsapp',
            'external_thread_id' => $threadKey,
        ], [
            'external_user_id' => $rawWaId,
            'metadata'         => array_filter([
                'display_phone_number' => $metadata['display_phone_number'] ?? null,
                'phone_number_id'      => $metadata['phone_number_id']      ?? null,
            ], static fn ($value) => $value !== null),
        ]);

        // ── 5. Store inbound message ───────────────────────────────────────────
        $text = $this->extractText($message);
        $type = $message['type'] ?? 'text';

        $saved = $service->addMessage($conversation, [
            'sender_type'       => 'visitor',
            'text'              => $text,
            'language'          => null,
            'channel'           => 'whatsapp',
            'external_message_id' => $externalMessageId,
            'message_type'      => $type,
            'metadata'          => [
                'whatsapp' => [
                    'raw'              => $message,
                    'normalized_phone' => $normalizedPhone,
                ],
            ],
            'contact' => [
                'whatsapp_user_id' => $rawWaId,
                'phone'            => $normalizedPhone,
            ],
            'allow_blocked_contacts' => true,
        ], $request, null);

        if ($text && $this->isOptOut($text)) {
            $this->applyOptOut($conversation->contact, $rawWaId);
            return; // Do not generate AI reply for opt-out messages.
        }

        if ($externalMessageId) {
            $saved->status        = 'received';
            $saved->delivery_state = 'received';
            $saved->save();
        }

        // ── 6. Generate AI reply and send it back via WhatsApp ─────────────────
        // Only generate a reply when there is actual text to respond to.
        // The AI message is stored with channel='whatsapp' so that
        // ChatConversationService::addMessage() dispatches SendWhatsAppMessage.
        if ($text) {
            try {
                $freshConversation = $conversation->fresh();
                if ($freshConversation && $ai->shouldAutoReply($freshConversation)) {
                    $ai->generateForVisitorMessage(
                        $freshConversation,
                        $saved,
                        $request,
                        ['whatsapp_channel' => 'whatsapp'] // hint: store reply as whatsapp channel
                    );
                }
            } catch (\Throwable $e) {
                Log::error('WhatsApp AI reply failed', [
                    'conversation_id' => $conversation->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }
    }

    private function handleStatus(array $entry): void
    {
        $status   = $entry['status']   ?? [];
        $metadata = $entry['metadata'] ?? [];
        $externalId = $status['id'] ?? null;
        if (! $externalId) {
            return;
        }

        $message = Message::where('external_message_id', $externalId)->first();
        if (! $message) {
            $message = $this->resolveStatusFallbackMessage($status, $metadata);
            if ($message) {
                $message->external_message_id = $externalId;
            }
        }
        if (! $message) {
            return;
        }

        $state = $status['status'] ?? null;
        if (! $state) {
            return;
        }

        $message->status        = $state;
        $message->delivery_state = $state;
        if ($state === 'delivered') {
            $message->delivered_at = $this->statusTimestamp($status['timestamp'] ?? null) ?? now();
        }
        if ($state === 'read') {
            $message->read_at = $this->statusTimestamp($status['timestamp'] ?? null) ?? now();
        }

        $message->metadata = array_merge($message->metadata ?? [], [
            'whatsapp' => array_merge($message->metadata['whatsapp'] ?? [], [
                'last_status'    => $state,
                'status_payload' => $status,
                'metadata'       => $metadata,
            ]),
        ]);

        if ($state === 'failed') {
            $message->metadata = array_merge($message->metadata ?? [], [
                'whatsapp_error' => $status['errors'] ?? null,
            ]);
        }
        $message->save();
    }

    /**
     * Resolve a registered User by their WhatsApp/phone number.
     * Tries both the normalized E.164 form and the raw wa_id.
     */
    private function resolveUserByPhone(string $normalizedPhone, string $rawWaId): ?User
    {
        return User::query()
            ->where(function ($query) use ($normalizedPhone, $rawWaId) {
                $query->where('phone', $normalizedPhone);
                if ($rawWaId !== $normalizedPhone) {
                    $query->orWhere('phone', $rawWaId)
                          ->orWhere('phone', '+' . $rawWaId);
                }
            })
            ->first();
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

    private function resolveStatusFallbackMessage(array $status, array $metadata): ?Message
    {
        $recipientId = (string) ($status['recipient_id'] ?? '');
        if ($recipientId === '') {
            return null;
        }

        $phoneNumberId      = $metadata['phone_number_id']      ?? null;
        $displayPhoneNumber = $metadata['display_phone_number'] ?? null;

        $channel = HarborChannel::query()
            ->where('channel', 'whatsapp')
            ->where('provider', '360dialog')
            ->where('status', 'active')
            ->where(function ($query) use ($phoneNumberId, $displayPhoneNumber) {
                if ($phoneNumberId) {
                    $query->where('metadata->phone_number_id', $phoneNumberId);
                }
                if ($displayPhoneNumber) {
                    $query->orWhere('from_number', $displayPhoneNumber);
                }
            })
            ->first();

        return Message::query()
            ->whereNull('external_message_id')
            ->where('channel', 'whatsapp')
            ->where('sender_type', '!=', 'visitor')
            ->whereIn('status', [null, 'queued', 'sent'])
            ->when($channel, fn ($query) => $query->whereHas(
                'conversation',
                fn ($q) => $q->where('location_id', $channel->harbor_id)
            ))
            ->whereHas(
                'conversation.contact',
                fn ($query) => $query->where('whatsapp_user_id', $recipientId)
            )
            ->latest('created_at')
            ->first();
    }

    private function statusTimestamp(mixed $timestamp): ?Carbon
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
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

    private function applyOptOut(?\App\Models\Contact $contact, string $waId): void
    {
        if ($contact) {
            $contact->do_not_contact          = true;
            $contact->consent_service_messages = false;
            $contact->save();
        }

        BlockedContact::updateOrCreate([
            'type'  => 'whatsapp',
            'value' => $waId,
        ], [
            'reason'         => 'opt_out',
            'blocked_until'  => null,
        ]);

        Log::info('WhatsApp opt-out applied', ['wa_id' => $waId]);
    }

    private function fakeRequest(): Request
    {
        $request = Request::create('/webhooks/whatsapp/360dialog', 'POST', []);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        return $request;
    }
}
