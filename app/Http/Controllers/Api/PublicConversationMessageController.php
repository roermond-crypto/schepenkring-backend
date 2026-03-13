<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatConversationService;
use App\Support\CopilotLanguage;
use Illuminate\Http\Request;

class PublicConversationMessageController extends Controller
{
    public function store(
        $conversationId,
        Request $request,
        CopilotLanguage $language,
        ChatConversationService $service
    )
    {
        $conversation = Conversation::findOrFail($conversationId);

        $validated = $request->validate([
            'body' => 'required|string',
            'client_message_id' => 'required|string'
        ]);

        // Deduplicate using combination of conversation_id and client_message_id
        $existingMessage = Message::where('conversation_id', $conversationId)
            ->where('client_message_id', $validated['client_message_id'])
            ->first();

        if ($existingMessage) {
            $existingLanguage = $language->normalize($existingMessage->language) ?? $conversation->language_preferred ?? 'en';

            return response()
                ->json([
                    'message' => $existingMessage,
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

        return response()
            ->json([
                'message' => $message,
                'language' => $resolvedLanguage['language'],
                'header_language' => $resolvedLanguage['header_language'],
                'language_detected_from_input' => $resolvedLanguage['detected_from_input'],
            ], 201)
            ->header('Content-Language', $resolvedLanguage['language'])
            ->header('X-Header-Language', $resolvedLanguage['header_language']);
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
}
