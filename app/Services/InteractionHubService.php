<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\InteractionEventType;
use App\Models\InteractionTimelineEntry;
use App\Models\Message;
use App\Models\EmailLog;

class InteractionHubService
{
    public function recordMessage(Message $message, ?Conversation $conversation = null): InteractionTimelineEntry
    {
        $conversation = $conversation ?: $message->conversation;
        $contactId = $conversation?->contact_id;
        $userId = $conversation?->user_id ?? $conversation?->contact?->user_id;

        $channel = $message->channel === 'web' ? 'chat' : $message->channel;
        $direction = $message->sender_type === 'visitor' ? 'inbound' : 'outbound';

        return InteractionTimelineEntry::create([
            'user_id' => $userId,
            'contact_id' => $contactId,
            'conversation_id' => $conversation?->id,
            'channel' => $channel,
            'direction' => $direction,
            'title' => $message->message_type,
            'body' => $message->text,
            'metadata' => $message->metadata,
            'template_id' => $message->metadata['template_id'] ?? null,
            'template_version' => $message->metadata['template_version'] ?? null,
            'source_type' => Message::class,
            'source_id' => $message->id,
            'occurred_at' => $message->created_at,
        ]);
    }

    public function recordConversationEvent(Conversation $conversation, string $eventKey, array $payload = []): InteractionTimelineEntry
    {
        $eventType = InteractionEventType::where('key', $eventKey)->first();

        return InteractionTimelineEntry::create([
            'user_id' => $conversation->user_id ?? $conversation->contact?->user_id,
            'contact_id' => $conversation->contact_id,
            'conversation_id' => $conversation->id,
            'event_type_id' => $eventType?->id,
            'channel' => 'system',
            'direction' => 'system',
            'title' => $eventKey,
            'body' => null,
            'metadata' => $payload ?: null,
            'source_type' => Conversation::class,
            'source_id' => $conversation->id,
            'occurred_at' => now(),
        ]);
    }

    public function recordEmail(EmailLog $log): InteractionTimelineEntry
    {
        return InteractionTimelineEntry::create([
            'user_id' => $log->user_id,
            'contact_id' => $log->contact_id,
            'event_type_id' => $log->event_type_id,
            'channel' => 'email',
            'direction' => 'outbound',
            'title' => $log->subject,
            'body' => null,
            'metadata' => $log->metadata,
            'template_id' => $log->template_id,
            'template_version' => $log->template_version,
            'source_type' => EmailLog::class,
            'source_id' => (string) $log->id,
            'occurred_at' => $log->sent_at ?? $log->created_at,
        ]);
    }
}
