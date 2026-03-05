<?php

namespace App\Http\Controllers;

use App\Models\ChatFaq;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatAccessService;
use App\Services\ChatConversationService;
use App\Services\ChatFaqPineconeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ChatMessageController extends Controller
{
    public function store(Request $request, string $conversationId, ChatAccessService $access, ChatConversationService $service)
    {
        $conversation = Conversation::findOrFail($conversationId);

        $user = $request->user();
        if ($user && !$access->canAccessConversation($user, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->validate([
            'sender_type' => 'nullable|string|in:visitor,admin,ai,system',
            'text' => 'nullable|string',
            'language' => 'nullable|string|max:5',
            'channel' => 'nullable|string|max:20',
            'external_message_id' => 'nullable|string|max:100',
            'message_type' => 'nullable|string|max:20',
            'metadata' => 'nullable|array',
            'metadata.to_number' => 'nullable|string|max:32',
            'metadata.phone_number' => 'nullable|string|max:32',
            'attachments' => 'nullable|array',
            'attachments.*.storage_key' => 'required_with:attachments|string',
            'attachments.*.mime_type' => 'required_with:attachments|string',
            'attachments.*.size' => 'required_with:attachments|integer',
            'attachments.*.checksum' => 'nullable|string',
            'contact' => 'nullable|array',
            'contact.name' => 'nullable|string|max:255',
            'contact.email' => 'nullable|email',
            'contact.phone' => 'nullable|string|max:50',
            'contact.whatsapp_user_id' => 'nullable|string|max:100',
            'contact.language_preferred' => 'nullable|string|max:5',
            'visitor_id' => 'nullable|string|max:64',
            'session_jwt' => 'nullable|string',
        ]);

        $messageType = $payload['message_type'] ?? 'text';
        if (empty($payload['text']) && empty($payload['attachments']) && $messageType !== 'call') {
            return response()->json(['message' => 'Message text or attachments required'], 422);
        }

        if (!$user) {
            $visitorId = $payload['visitor_id'] ?? null;
            if (!empty($payload['session_jwt'])) {
                try {
                    $decoded = json_decode(Crypt::decryptString($payload['session_jwt']), true);
                    $visitorId = $decoded['visitor_id'] ?? $visitorId;
                } catch (\Throwable $e) {
                    return response()->json(['message' => 'Invalid session token'], 401);
                }
            }

            if ($conversation->visitor_id && $visitorId && $conversation->visitor_id !== $visitorId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $message = $service->addMessage($conversation, $payload, $request, $user);

        return response()->json($message, 201);
    }

    public function thumbsUp(Request $request, string $messageId, ChatAccessService $access, ChatFaqPineconeService $pinecone)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $message = Message::with('conversation')->findOrFail($messageId);
        if (!$access->canAccessConversation($user, $message->conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $conversation = $message->conversation;
        $question = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'visitor')
            ->where('created_at', '<=', $message->created_at)
            ->orderByDesc('created_at')
            ->first();

        if (!$question || !$message->text) {
            return response()->json(['message' => 'Unable to derive FAQ pair'], 422);
        }

        $language = $message->language ?? $question->language ?? $conversation->language_preferred;

        $chatFaq = ChatFaq::where('harbor_id', $conversation->harbor_id)
            ->where('language', $language)
            ->where('question', $question->text)
            ->where('best_answer', $message->text)
            ->first();

        if (!$chatFaq) {
            $chatFaq = ChatFaq::create([
                'harbor_id' => $conversation->harbor_id,
                'language' => $language,
                'question' => $question->text,
                'best_answer' => $message->text,
                'thumbs_up_count' => 1,
                'source_conversation_id' => $conversation->id,
                'created_by_admin_id' => $user->id,
            ]);
        } else {
            $chatFaq->increment('thumbs_up_count');
        }

        $pinecone->upsert($chatFaq);

        return response()->json([
            'message' => 'Saved for training',
            'chat_faq' => $chatFaq,
        ]);
    }
}
