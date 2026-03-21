<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatAiReplyService;
use App\Services\ChatConversationService;
use App\Support\CopilotLanguage;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PublicConversationMessageController extends Controller
{
    public function show($conversationId, Request $request)
    {
        $conversation = Conversation::with([
            'contact',
            'lead',
            'messages' => function ($query) {
                $query->with(['attachments', 'employee:id,name,email'])
                    ->orderBy('created_at', 'asc')
                    ->limit(200);
            },
        ])->findOrFail($conversationId);

        $validated = $request->validate([
            'visitor_id' => 'nullable|string|max:64',
            'session_jwt' => 'nullable|string',
        ]);

        $this->assertConversationAccess($conversation, $validated);

        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages,
        ]);
    }

    public function store(
        $conversationId,
        Request $request,
        CopilotLanguage $language,
        ChatConversationService $service,
        ChatAiReplyService $ai
    )
    {
        return $this->handleIncomingMessage($conversationId, $request, $language, $service, $ai);
    }

    public function ask(
        $conversationId,
        Request $request,
        CopilotLanguage $language,
        ChatConversationService $service,
        ChatAiReplyService $ai
    ) {
        return $this->handleIncomingMessage($conversationId, $request, $language, $service, $ai);
    }

    public function updateLead($conversationId, Request $request)
    {
        $conversation = Conversation::with('lead')->findOrFail($conversationId);

        if (!$conversation->lead) {
            return response()->json(['error' => 'No lead associated with this conversation.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email',
            'phone' => 'sometimes|string',
        ]);

        $conversation->lead->update($validated);

        return response()->json(['lead' => $conversation->lead], 200);
    }

    private function handleIncomingMessage(
        string $conversationId,
        Request $request,
        CopilotLanguage $language,
        ChatConversationService $service,
        ChatAiReplyService $ai
    ) {
        $conversation = Conversation::with(['contact', 'lead'])->findOrFail($conversationId);

        $validated = $request->validate([
            'body' => 'required|string',
            'client_message_id' => 'required|string',
            'visitor_id' => 'nullable|string|max:64',
            'session_jwt' => 'nullable|string',
        ]);

        $this->assertConversationAccess($conversation, $validated);

        $existingMessage = Message::where('conversation_id', $conversationId)
            ->where('client_message_id', $validated['client_message_id'])
            ->first();

        if ($existingMessage) {
            $existingLanguage = $language->normalize($existingMessage->language) ?? $conversation->language_preferred ?? 'en';

            return response()
                ->json([
                    'conversation' => $conversation->fresh(['contact', 'lead']),
                    'message' => $existingMessage->loadMissing(['attachments', 'employee:id,name,email']),
                    'ai_message' => $this->findExistingAiReply($conversationId, $existingMessage->id),
                    'language' => $existingLanguage,
                    'header_language' => strtoupper($existingLanguage),
                    'language_detected_from_input' => false,
                ], 200)
                ->header('Content-Language', $existingLanguage)
                ->header('X-Header-Language', strtoupper($existingLanguage));
        }

        $resolvedLanguage = $language->resolve(
            $validated['body'],
            $conversation->language_preferred,
            $request->header('Accept-Language'),
            $conversation->contact?->language_preferred
        );

        $message = $service->addMessage($conversation, [
            'sender_type' => 'visitor',
            'text' => $validated['body'],
            'language' => $resolvedLanguage['language'],
            'client_message_id' => $validated['client_message_id'],
            'delivery_state' => 'sent',
        ], $request);

        $service->syncLanguageContext($conversation, $resolvedLanguage, null, 'visitor', true);

        $aiMessage = $ai->generateForVisitorMessage($conversation->fresh(), $message, $request);

        return response()
            ->json([
                'conversation' => $conversation->fresh(['contact', 'lead']),
                'message' => $message->loadMissing(['attachments', 'employee:id,name,email']),
                'ai_message' => $aiMessage,
                'language' => $resolvedLanguage['language'],
                'header_language' => $resolvedLanguage['header_language'],
                'language_detected_from_input' => $resolvedLanguage['detected_from_input'],
            ], 201)
            ->header('Content-Language', $resolvedLanguage['language'])
            ->header('X-Header-Language', $resolvedLanguage['header_language']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertConversationAccess(Conversation $conversation, array $validated): void
    {
        $visitorId = $validated['visitor_id'] ?? null;

        if (! empty($validated['session_jwt'])) {
            try {
                $decoded = json_decode(Crypt::decryptString($validated['session_jwt']), true);
                $visitorId = $decoded['visitor_id'] ?? $visitorId;
            } catch (\Throwable $e) {
                throw new HttpResponseException(response()->json(['message' => 'Invalid session token'], 401));
            }
        }

        if ($conversation->visitor_id && $visitorId && $conversation->visitor_id !== $visitorId) {
            throw new HttpResponseException(response()->json(['message' => 'Forbidden'], 403));
        }

        if (! $conversation->visitor_id && $visitorId) {
            $conversation->visitor_id = $visitorId;
            $conversation->save();
        }
    }

    private function findExistingAiReply(string $conversationId, string $visitorMessageId): ?Message
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('sender_type', 'ai')
            ->orderByDesc('created_at')
            ->get()
            ->first(function (Message $message) use ($visitorMessageId) {
                return data_get($message->metadata, 'in_reply_to_message_id') === $visitorMessageId;
            });
    }
}
