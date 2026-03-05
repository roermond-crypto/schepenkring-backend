<?php

namespace App\Http\Controllers;

use App\Models\ChannelIdentity;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatConversationService;
use Illuminate\Http\Request;

class ChatAdapterController extends Controller
{
    public function whatsappInbound(Request $request, ChatConversationService $service)
    {
        $this->authorizeAdapter($request);
        return $this->handleInbound($request, $service, 'whatsapp');
    }

    public function emailInbound(Request $request, ChatConversationService $service)
    {
        $this->authorizeAdapter($request);
        return $this->handleInbound($request, $service, 'email');
    }

    private function handleInbound(Request $request, ChatConversationService $service, string $type)
    {
        $payload = $request->validate([
            'external_thread_id' => 'nullable|string|max:255',
            'external_user_id' => 'nullable|string|max:255',
            'external_message_id' => 'nullable|string|max:255',
            'text' => 'nullable|string',
            'language' => 'nullable|string|max:5',
            'message_type' => 'nullable|string|max:30',
            'harbor_id' => 'nullable|integer',
            'contact' => 'nullable|array',
            'contact.name' => 'nullable|string|max:255',
            'contact.email' => 'nullable|email',
            'contact.phone' => 'nullable|string|max:50',
            'contact.whatsapp_user_id' => 'nullable|string|max:100',
            'attachments' => 'nullable|array',
            'attachments.*.storage_key' => 'required_with:attachments|string',
            'attachments.*.mime_type' => 'required_with:attachments|string',
            'attachments.*.size' => 'required_with:attachments|integer',
            'attachments.*.checksum' => 'nullable|string',
        ]);

        if (!empty($payload['external_message_id'])) {
            $exists = Message::where('external_message_id', $payload['external_message_id'])->exists();
            if ($exists) {
                return response()->json(['message' => 'Duplicate message ignored'], 200);
            }
        }

        $conversation = $this->findConversation($type, $payload['external_thread_id'] ?? null, $payload['external_user_id'] ?? null);

        if (!$conversation) {
            $conversation = $service->createConversation([
                'contact' => $payload['contact'] ?? null,
                'channel_origin' => $type,
                'harbor_id' => $payload['harbor_id'] ?? null,
                'language_preferred' => $payload['language'] ?? null,
                'reuse' => true,
            ], $request);
        }

        $identity = ChannelIdentity::updateOrCreate([
            'conversation_id' => $conversation->id,
            'type' => $type,
            'external_thread_id' => $payload['external_thread_id'] ?? null,
        ], [
            'external_user_id' => $payload['external_user_id'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ]);

        $messagePayload = [
            'sender_type' => 'visitor',
            'text' => $payload['text'] ?? null,
            'language' => $payload['language'] ?? null,
            'channel' => $type,
            'external_message_id' => $payload['external_message_id'] ?? null,
            'message_type' => $payload['message_type'] ?? 'text',
            'attachments' => $payload['attachments'] ?? null,
            'contact' => $payload['contact'] ?? null,
            'metadata' => [
                'channel_identity_id' => $identity->id,
            ],
        ];

        $message = $service->addMessage($conversation, $messagePayload, $request, null);

        $service->recordEvent($conversation, 'webhook_received', [
            'type' => $type,
            'external_message_id' => $payload['external_message_id'] ?? null,
            'idempotency_key' => $request->header('X-Idempotency-Key'),
        ]);

        return response()->json([
            'message' => 'Inbound processed',
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ], 201);
    }

    private function findConversation(string $type, ?string $threadId, ?string $userId): ?Conversation
    {
        if ($threadId) {
            $identity = ChannelIdentity::where('type', $type)
                ->where('external_thread_id', $threadId)
                ->first();
            if ($identity) {
                return $identity->conversation;
            }
        }

        if ($userId) {
            $identity = ChannelIdentity::where('type', $type)
                ->where('external_user_id', $userId)
                ->first();
            if ($identity) {
                return $identity->conversation;
            }
        }

        return null;
    }

    private function authorizeAdapter(Request $request): void
    {
        $secret = env('CHAT_ADAPTER_SECRET');
        if (!$secret) {
            return;
        }

        $incoming = $request->header('X-Chat-Adapter-Secret');
        if (!$incoming || $incoming !== $secret) {
            abort(401, 'Unauthorized adapter.');
        }
    }
}
