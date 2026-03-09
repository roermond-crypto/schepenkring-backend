<?php

namespace App\Services;

use App\Jobs\InitiateOutboundCall;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\CopilotLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatConversationService
{
    public function __construct(
        private ChatContactService $contactService,
        private ChatAbuseService $abuseService,
        private CopilotLanguage $language
    ) {
    }

    public function createConversation(array $payload, Request $request, ?User $user = null): Conversation
    {
        $contact = $this->contactService->resolveContact($payload['contact'] ?? null, $user);

        $email = $contact?->email ?? ($payload['contact']['email'] ?? null);
        $phone = $contact?->phone ?? ($payload['contact']['phone'] ?? null);
        $whatsappId = $contact?->whatsapp_user_id ?? ($payload['contact']['whatsapp_user_id'] ?? null);

        if (! ($payload['allow_blocked_contacts'] ?? false)) {
            $this->abuseService->ensureNotBlocked($email, $phone, $whatsappId, $request->ip());
        }
        if (! ($payload['skip_rate_limit'] ?? false)) {
            $this->abuseService->rateLimit($request, $payload['visitor_id'] ?? null, $email ?? $phone ?? $whatsappId);
        }

        $visitorId = $payload['visitor_id'] ?? null;
        $boatId = $payload['boat_id'] ?? null;
        $harborId = $payload['harbor_id'] ?? $payload['widget_harbor_id'] ?? null;
        if (! $harborId) {
            $harborId = \App\Models\Location::query()->value('id') ?? 1;
        }
        $harborId = (int) $harborId;

        $reuse = (bool) ($payload['reuse'] ?? true);
        if ($reuse) {
            $existing = $this->findReusableConversation($visitorId, $contact?->id, $boatId, $harborId);
            if ($existing) {
                return $existing;
            }
        }

        $channelOrigin = $payload['channel_origin'] ?? 'web_widget';

        $conversation = Conversation::create([
            'user_id' => $user?->id ?? $contact?->user_id,
            'location_id' => $harborId,
            'boat_id' => $boatId,
            'contact_id' => $contact?->id,
            'visitor_id' => $visitorId,
            'status' => $payload['status'] ?? 'open',
            'priority' => $payload['priority'] ?? 'normal',
            'channel' => $channelOrigin,
            'channel_origin' => $channelOrigin,
            'ai_mode' => $payload['ai_mode'] ?? 'auto',
            'language_preferred' => $this->language->normalize($payload['language_preferred'] ?? $contact?->language_preferred),
            'language_detected' => $this->language->normalize($payload['language_detected'] ?? null),
            'page_url' => $payload['page_url'] ?? null,
            'utm_source' => $payload['utm_source'] ?? null,
            'utm_medium' => $payload['utm_medium'] ?? null,
            'utm_campaign' => $payload['utm_campaign'] ?? null,
            'ref_code' => $payload['ref_code'] ?? null,
            'first_response_due_at' => null,
        ]);

        if ($user && $this->isStaff($user)) {
            $conversation->assigned_to = $user->id;
            $conversation->assigned_employee_id = $conversation->assigned_employee_id ?? $user->id;
            $conversation->save();
        }

        return $conversation;
    }

    public function addMessage(Conversation $conversation, array $payload, Request $request, ?User $user = null): Message
    {
        $contact = $conversation->contact;
        $email = $contact?->email ?? ($payload['contact']['email'] ?? null);
        $phone = $contact?->phone ?? ($payload['contact']['phone'] ?? null);
        $whatsappId = $contact?->whatsapp_user_id ?? ($payload['contact']['whatsapp_user_id'] ?? null);

        if (! ($payload['allow_blocked_contacts'] ?? false)) {
            $this->abuseService->ensureNotBlocked($email, $phone, $whatsappId, $request->ip());
        }
        if (! ($payload['skip_rate_limit'] ?? false)) {
            $this->abuseService->rateLimit($request, $conversation->visitor_id, $email ?? $phone ?? $whatsappId);
        }

        if ($payload['contact'] ?? null) {
            $contact = $this->contactService->resolveContact($payload['contact'], $user);
            if ($contact && $contact->id !== $conversation->contact_id) {
                $conversation->contact_id = $contact->id;
            }
            if (! $conversation->user_id && $contact?->user_id) {
                $conversation->user_id = $contact->user_id;
            }
            $conversation->save();
        }

        $senderType = $payload['sender_type'] ?? ($user ? 'admin' : 'visitor');
        if ($user && $this->isStaff($user) && $senderType === 'visitor') {
            $senderType = 'admin';
        }

        $metadata = $payload['metadata'] ?? null;
        if ($user && (($payload['message_type'] ?? null) === 'call')) {
            $metadata = array_merge($metadata ?? [], [
                'initiated_by_user_id' => $user->id,
            ]);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'employee_id' => $user?->id,
            'text' => $payload['text'] ?? null,
            'body' => $payload['text'] ?? null,
            'language' => $this->language->normalize($payload['language'] ?? $conversation->language_preferred),
            'channel' => $payload['channel'] ?? 'web',
            'external_message_id' => $payload['external_message_id'] ?? null,
            'message_type' => $payload['message_type'] ?? 'text',
            'status' => $payload['status'] ?? null,
            'metadata' => $metadata,
        ]);

        if ($message->channel === 'whatsapp' && $message->sender_type !== 'visitor' && ! $message->status) {
            $message->status = 'queued';
            $message->save();
        }

        $attachments = $payload['attachments'] ?? [];
        $this->storeAttachments($message, $attachments);

        $now = now();
        $conversation->last_message_at = $now;
        if ($message->sender_type === 'visitor') {
            $conversation->last_customer_message_at = $now;
            if ($message->channel === 'whatsapp') {
                $conversation->last_inbound_at = $now;
                $conversation->window_expires_at = $now->copy()->addHours(24);
            }
        } else {
            $conversation->last_staff_message_at = $now;
        }
        $conversation->save();

        if ($message->sender_type !== 'visitor' && $message->channel === 'whatsapp') {
            SendWhatsAppMessage::dispatch($message->id);
        }

        if ($message->sender_type !== 'visitor' && $message->message_type === 'call') {
            if (! $message->status) {
                $message->status = 'queued';
                $message->save();
            }
            InitiateOutboundCall::dispatch($message->id);
        }

        return $message;
    }

    public function syncLanguageContext(Conversation $conversation, array $resolvedLanguage, ?User $user = null, ?string $senderType = null, bool $persistPreference = false): void
    {
        $language = $this->language->normalize($resolvedLanguage['language'] ?? null);
        if (! $language) {
            return;
        }

        $senderType = $senderType ?: 'visitor';
        $isHumanMessage = ! in_array($senderType, ['ai', 'system'], true);
        $conversationChanged = false;

        if (($persistPreference || empty($conversation->language_preferred)) && $isHumanMessage && $conversation->language_preferred !== $language) {
            $conversation->language_preferred = $language;
            $conversationChanged = true;
        }

        if (! empty($resolvedLanguage['detected_from_input']) && $conversation->language_detected !== $language) {
            $conversation->language_detected = $language;
            $conversationChanged = true;
        }

        if ($conversationChanged) {
            $conversation->save();
        }

        $conversation->loadMissing('contact');
        if ($conversation->contact && $senderType === 'visitor' && ($persistPreference || !empty($resolvedLanguage['detected_from_input']))) {
            if ($conversation->contact->language_preferred !== $language) {
                $conversation->contact->language_preferred = $language;
                $conversation->contact->save();
            }
        }

        if ($user && $isHumanMessage && ($persistPreference || !empty($resolvedLanguage['detected_from_input'])) && $user->locale !== $language) {
            $user->forceFill([
                'locale' => $language,
            ])->saveQuietly();
        }
    }

    public function recordEvent(Conversation $conversation, string $type, array $payload = []): void
    {
        Log::info('Conversation event', [
            'conversation_id' => $conversation->id,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    private function findReusableConversation(?string $visitorId, ?string $contactId, ?int $boatId, int $harborId): ?Conversation
    {
        if (! $visitorId && ! $contactId) {
            return null;
        }

        $query = Conversation::query()
            ->where('location_id', $harborId)
            ->where('status', 'open');

        if ($boatId) {
            $query->where('boat_id', $boatId);
        }

        $query->where(function ($sub) use ($visitorId, $contactId) {
            if ($visitorId) {
                $sub->orWhere('visitor_id', $visitorId);
            }
            if ($contactId) {
                $sub->orWhere('contact_id', $contactId);
            }
        });

        return $query->orderByDesc('updated_at')->first();
    }

    private function storeAttachments(Message $message, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            Attachment::create([
                'id' => (string) Str::uuid(),
                'message_id' => $message->id,
                'storage_key' => $attachment['storage_key'],
                'mime_type' => $attachment['mime_type'],
                'size' => $attachment['size'],
                'checksum' => $attachment['checksum'] ?? null,
            ]);
        }
    }

    private function isStaff(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }
}
