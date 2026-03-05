<?php

namespace App\Services;

use App\Jobs\GenerateChatAiReply;
use App\Jobs\ProcessChatAttachment;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationParticipant;
use App\Models\HarborChatSetting;
use App\Models\Message;
use App\Models\User;
use App\Models\Yacht;
use App\Services\NotificationDispatchService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ChatConversationService
{
    public function __construct(
        private ChatContactService $contactService,
        private ChatAbuseService $abuseService,
        private NotificationDispatchService $notifier,
        private InteractionHubService $hub
    ) {
    }

    public function createConversation(array $payload, Request $request, ?User $user = null): Conversation
    {
        $contact = $this->contactService->resolveContact($payload['contact'] ?? null, $user);

        $email = $contact?->email ?? ($payload['contact']['email'] ?? null);
        $phone = $contact?->phone ?? ($payload['contact']['phone'] ?? null);
        $whatsappId = $contact?->whatsapp_user_id ?? ($payload['contact']['whatsapp_user_id'] ?? null);

        if (!($payload['allow_blocked_contacts'] ?? false)) {
            $this->abuseService->ensureNotBlocked($email, $phone, $whatsappId, $request->ip());
        }
        if (!($payload['skip_rate_limit'] ?? false)) {
            $this->abuseService->rateLimit($request, $payload['visitor_id'] ?? null, $email ?? $phone ?? $whatsappId);
        }

        $visitorId = $payload['visitor_id'] ?? null;
        $boatId = $payload['boat_id'] ?? $this->resolveBoatId($payload['page_url'] ?? null);
        $harborId = $this->resolveHarborId($payload, $boatId, $contact);

        $reuse = (bool) ($payload['reuse'] ?? true);
        if ($reuse) {
            $existing = $this->findReusableConversation($visitorId, $contact?->id, $boatId);
            if ($existing) {
                return $existing;
            }
        }

        $settings = HarborChatSetting::where('harbor_id', $harborId)->first();
        $aiMode = $payload['ai_mode'] ?? $settings?->ai_mode_default ?? 'auto';

        $conversation = Conversation::create([
            'user_id' => $user?->id ?? $contact?->user_id,
            'harbor_id' => $harborId,
            'boat_id' => $boatId,
            'contact_id' => $contact?->id,
            'visitor_id' => $visitorId,
            'status' => $payload['status'] ?? 'open',
            'priority' => $payload['priority'] ?? 'normal',
            'channel_origin' => $payload['channel_origin'] ?? 'web_widget',
            'ai_mode' => $aiMode,
            'language_preferred' => $payload['language_preferred'] ?? $contact?->language_preferred,
            'language_detected' => $payload['language_detected'] ?? null,
            'page_url' => $payload['page_url'] ?? null,
            'utm_source' => $payload['utm_source'] ?? null,
            'utm_medium' => $payload['utm_medium'] ?? null,
            'utm_campaign' => $payload['utm_campaign'] ?? null,
            'ref_code' => $payload['ref_code'] ?? null,
            'first_response_due_at' => $this->firstResponseDueAt($settings),
        ]);

        $this->recordEvent($conversation, 'created', [
            'channel_origin' => $conversation->channel_origin,
            'visitor_id' => $visitorId,
        ]);

        if ($user && $this->isStaff($user)) {
            $this->assignConversation($conversation, $user->id, $user->id);
        }

        if (!($payload['skip_auto_messages'] ?? false)) {
            $this->maybeSendBoatGreeting($conversation);
            $this->maybeSendOfflineMessage($conversation, $settings);
        }

        $this->notifyStaffNewConversation($conversation);

        return $conversation;
    }

    public function addMessage(Conversation $conversation, array $payload, Request $request, ?User $user = null): Message
    {
        $contact = $conversation->contact;
        $email = $contact?->email ?? ($payload['contact']['email'] ?? null);
        $phone = $contact?->phone ?? ($payload['contact']['phone'] ?? null);
        $whatsappId = $contact?->whatsapp_user_id ?? ($payload['contact']['whatsapp_user_id'] ?? null);

        if (!($payload['allow_blocked_contacts'] ?? false)) {
            $this->abuseService->ensureNotBlocked($email, $phone, $whatsappId, $request->ip());
        }
        if (!($payload['skip_rate_limit'] ?? false)) {
            $this->abuseService->rateLimit($request, $conversation->visitor_id, $email ?? $phone ?? $whatsappId);
        }

        if ($payload['contact'] ?? null) {
            $contact = $this->contactService->resolveContact($payload['contact'], $user);
            if ($contact && $contact->id !== $conversation->contact_id) {
                $conversation->contact_id = $contact->id;
            }
            if (!$conversation->user_id && $contact?->user_id) {
                $conversation->user_id = $contact->user_id;
            }
            $conversation->save();
        }

        $metadata = $payload['metadata'] ?? null;
        if ($user && (($payload['message_type'] ?? null) === 'call')) {
            $metadata = array_merge($metadata ?? [], [
                'initiated_by_user_id' => $user->id,
            ]);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $payload['sender_type'] ?? ($user ? 'admin' : 'visitor'),
            'text' => $payload['text'] ?? null,
            'language' => $payload['language'] ?? $conversation->language_preferred,
            'channel' => $payload['channel'] ?? 'web',
            'external_message_id' => $payload['external_message_id'] ?? null,
            'message_type' => $payload['message_type'] ?? 'text',
            'status' => $payload['status'] ?? null,
            'metadata' => $metadata,
        ]);

        $this->hub->recordMessage($message, $conversation);

        if ($message->channel === 'whatsapp' && $message->sender_type !== 'visitor' && !$message->status) {
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
            if (!$conversation->first_response_due_at) {
                $settings = HarborChatSetting::where('harbor_id', $conversation->harbor_id)->first();
                $conversation->first_response_due_at = $this->firstResponseDueAt($settings);
            }
        } else {
            $conversation->last_staff_message_at = $now;
            if ($message->message_type !== 'call' && $conversation->ai_mode === 'auto') {
                $conversation->ai_mode = 'assist';
                $this->recordEvent($conversation, 'handoff', [
                    'trigger' => 'staff_reply',
                    'message_id' => $message->id,
                ]);
            }
        }
        $conversation->save();

        $this->recordEvent($conversation, 'message_created', [
            'message_id' => $message->id,
            'sender_type' => $message->sender_type,
            'channel' => $message->channel,
        ]);

        if ($message->sender_type !== 'visitor') {
            $this->recordEvent($conversation, 'send_attempt', [
                'message_id' => $message->id,
                'channel' => $message->channel,
            ]);
        }

        if ($message->sender_type !== 'visitor' && $message->channel === 'whatsapp') {
            \App\Jobs\SendWhatsAppMessage::dispatch($message->id);
        }

        if ($message->sender_type !== 'visitor' && $message->message_type === 'call') {
            if (!$message->status) {
                $message->status = 'queued';
                $message->save();
            }
            \App\Jobs\InitiateOutboundCall::dispatch($message->id);
        }

        if ($message->sender_type === 'visitor') {
            $this->notifyStaffNewMessage($conversation, $message);
            $this->triggerAiIfNeeded($conversation, $message);
        }

        return $message;
    }

    public function updateConversation(Conversation $conversation, array $payload, ?User $actor = null): Conversation
    {
        $originalStatus = $conversation->status;
        $originalHarbor = $conversation->harbor_id;
        $originalPriority = $conversation->priority;
        $originalAiMode = $conversation->ai_mode;

        if (isset($payload['status'])) {
            $conversation->status = $payload['status'];
        }
        if (isset($payload['priority'])) {
            $conversation->priority = $payload['priority'];
        }
        if (isset($payload['ai_mode'])) {
            $conversation->ai_mode = $payload['ai_mode'];
        }
        if (isset($payload['harbor_id'])) {
            $conversation->harbor_id = (int) $payload['harbor_id'];
        }

        if (isset($payload['assign_to'])) {
            $this->assignConversation($conversation, (int) $payload['assign_to'], $actor?->id);
        }

        $conversation->save();

        if ($originalStatus !== $conversation->status) {
            $this->recordEvent($conversation, 'status_changed', [
                'from' => $originalStatus,
                'to' => $conversation->status,
                'actor_id' => $actor?->id,
            ]);
        }

        if ($originalHarbor !== $conversation->harbor_id) {
            $this->recordEvent($conversation, 'harbor_changed', [
                'from' => $originalHarbor,
                'to' => $conversation->harbor_id,
                'actor_id' => $actor?->id,
            ]);
        }

        if ($originalPriority !== $conversation->priority) {
            $this->recordEvent($conversation, 'priority_changed', [
                'from' => $originalPriority,
                'to' => $conversation->priority,
                'actor_id' => $actor?->id,
            ]);
        }

        if ($originalAiMode !== $conversation->ai_mode) {
            $this->recordEvent($conversation, 'ai_mode_changed', [
                'from' => $originalAiMode,
                'to' => $conversation->ai_mode,
                'actor_id' => $actor?->id,
            ]);
        }

        return $conversation;
    }

    public function recordEvent(Conversation $conversation, string $type, array $payload = []): void
    {
        ConversationEvent::create([
            'conversation_id' => $conversation->id,
            'type' => $type,
            'payload' => $payload,
        ]);

        $this->hub->recordConversationEvent($conversation, $type, $payload);
    }

    private function resolveBoatId(?string $pageUrl): ?int
    {
        if (!$pageUrl) {
            return null;
        }

        if (preg_match('~/yachts/(\\d+)~', $pageUrl, $matches)) {
            return (int) $matches[1];
        }

        $yacht = Yacht::where('external_url', $pageUrl)
            ->orWhere('print_url', $pageUrl)
            ->first();

        return $yacht?->id;
    }

    private function resolveHarborId(array $payload, ?int $boatId, $contact): int
    {
        if ($boatId) {
            $yacht = Yacht::find($boatId);
            if ($yacht) {
                return (int) $yacht->user_id;
            }
        }

        if (!empty($payload['harbor_id'])) {
            return (int) $payload['harbor_id'];
        }

        if (!empty($payload['widget_harbor_id'])) {
            return (int) $payload['widget_harbor_id'];
        }

        if ($contact) {
            $lastConversation = Conversation::where('contact_id', $contact->id)
                ->orderBy('created_at', 'desc')
                ->first();
            if ($lastConversation) {
                return (int) $lastConversation->harbor_id;
            }
        }

        return 1;
    }

    private function findReusableConversation(?string $visitorId, ?string $contactId, ?int $boatId): ?Conversation
    {
        $query = Conversation::query()->where('status', '!=', 'solved');

        if ($visitorId) {
            $query->where('visitor_id', $visitorId);
        } elseif ($contactId) {
            $query->where('contact_id', $contactId);
        } else {
            return null;
        }

        if ($boatId) {
            $query->where('boat_id', $boatId);
        }

        return $query->orderBy('created_at', 'desc')->first();
    }

    private function assignConversation(Conversation $conversation, int $userId, ?int $actorId = null): void
    {
        $conversation->assigned_to = $userId;
        $conversation->save();

        ConversationParticipant::updateOrCreate([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
        ], [
            'role' => 'owner',
        ]);

        $this->recordEvent($conversation, 'assigned', [
            'assigned_to' => $userId,
            'actor_id' => $actorId,
        ]);
    }

    private function maybeSendBoatGreeting(Conversation $conversation): void
    {
        if (!$conversation->boat_id) {
            return;
        }

        $boat = $conversation->boat;
        if (!$boat) {
            return;
        }

        $brand = $boat->manufacturer ?? null;
        $model = $boat->model ?? null;
        $year = $boat->year ?? null;
        $nameParts = array_filter([$brand, $model, $year ? "({$year})" : null]);
        $label = !empty($nameParts) ? implode(' ', $nameParts) : ($boat->boat_name ?? 'this boat');

        $text = "Hi, interested in this {$label}?";

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'system',
            'text' => $text,
            'channel' => 'web',
            'message_type' => 'system',
            'metadata' => [
                'quick_replies' => [
                    'Is it still available?',
                    'Plan a viewing',
                    'Make an offer',
                ],
            ],
        ]);
    }

    private function maybeSendOfflineMessage(Conversation $conversation, ?HarborChatSetting $settings): void
    {
        $timezone = $settings?->timezone ?? env('CHAT_BUSINESS_TZ', 'Europe/Amsterdam');
        $start = $settings?->business_hours_start ?? env('CHAT_BUSINESS_HOURS_START', '09:00');
        $end = $settings?->business_hours_end ?? env('CHAT_BUSINESS_HOURS_END', '17:00');

        if (!$start || !$end) {
            return;
        }

        $now = now()->setTimezone($timezone);
        $startTime = Carbon::parse($start, $timezone)->setDate($now->year, $now->month, $now->day);
        $endTime = Carbon::parse($end, $timezone)->setDate($now->year, $now->month, $now->day);

        if ($now->between($startTime, $endTime)) {
            return;
        }

        $message = $settings?->offline_message ?: 'We are currently offline. We will get back to you as soon as possible.';

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'system',
            'text' => $message,
            'channel' => 'web',
            'message_type' => 'system',
        ]);
    }

    private function firstResponseDueAt(?HarborChatSetting $settings): ?Carbon
    {
        $minutes = $settings?->first_response_minutes ?? (int) env('CHAT_FIRST_RESPONSE_MINUTES', 30);
        if ($minutes <= 0) {
            return null;
        }

        return now()->addMinutes($minutes);
    }

    private function notifyStaffNewConversation(Conversation $conversation): void
    {
        $staff = $this->resolveStaffRecipients($conversation);
        foreach ($staff as $user) {
            $this->notifier->notifyUser(
                $user,
                'chat_new_conversation',
                'New Conversation',
                'A new chat conversation has started.',
                [
                    'conversation_id' => $conversation->id,
                    'harbor_id' => $conversation->harbor_id,
                ],
                null,
                true,
                true
            );
        }
    }

    private function notifyStaffNewMessage(Conversation $conversation, Message $message): void
    {
        $staff = $this->resolveStaffRecipients($conversation);
        foreach ($staff as $user) {
            $this->notifier->notifyUser(
                $user,
                'chat_new_message',
                'New Chat Message',
                $message->text ?? 'New message received.',
                [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                ],
                null,
                true,
                true
            );
        }
    }

    private function resolveStaffRecipients(Conversation $conversation)
    {
        $harborId = $conversation->harbor_id;

        $admins = User::where('role', 'Admin')->where('status', 'Active')->get();
        $harborAdmins = User::where('role', 'Partner')->where('id', $harborId)->where('status', 'Active')->get();
        $agents = User::where('role', 'Employee')
            ->where('partner_id', $harborId)
            ->where('status', 'Active')
            ->get();

        return $admins->merge($harborAdmins)->merge($agents)->unique('id');
    }

    private function storeAttachments(Message $message, array $attachments): void
    {
        if (empty($attachments)) {
            return;
        }

        $allowed = array_filter(array_map('trim', explode(',', env('CHAT_ATTACHMENT_ALLOWLIST', 'image/png,image/jpeg,image/webp,application/pdf'))));
        $maxSize = (int) env('CHAT_ATTACHMENT_MAX_SIZE', 15 * 1024 * 1024);

        foreach ($attachments as $attachment) {
            $mime = $attachment['mime_type'] ?? null;
            $size = (int) ($attachment['size'] ?? 0);

            if ($mime && !in_array($mime, $allowed, true)) {
                continue;
            }

            if ($size > $maxSize) {
                continue;
            }

            $record = Attachment::create([
                'message_id' => $message->id,
                'storage_key' => $attachment['storage_key'],
                'mime_type' => $mime ?? 'application/octet-stream',
                'size' => $size,
                'checksum' => $attachment['checksum'] ?? null,
            ]);

            ProcessChatAttachment::dispatch($record->id);
        }
    }

    private function triggerAiIfNeeded(Conversation $conversation, Message $message): void
    {
        $settings = HarborChatSetting::where('harbor_id', $conversation->harbor_id)->first();
        if ($settings && !$settings->ai_enabled) {
            return;
        }

        if ($conversation->ai_mode === 'off') {
            return;
        }

        GenerateChatAiReply::dispatch($conversation->id, $message->id);
    }

    private function isStaff(User $user): bool
    {
        $role = strtolower((string) $user->role);
        return in_array($role, ['admin', 'employee', 'partner'], true);
    }
}
