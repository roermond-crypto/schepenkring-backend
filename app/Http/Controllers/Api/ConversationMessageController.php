<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatConversationService;
use App\Support\CopilotLanguage;
use Illuminate\Http\Request;

class ConversationMessageController extends Controller
{
    public function index($conversationId, Request $request)
    {
        $conversation = Conversation::findOrFail($conversationId);
        
        $messages = $conversation->messages()
            ->with('employee')
            ->orderBy('created_at', 'asc')
            ->paginate($request->input('per_page', 50));

        return response()->json($messages);
    }

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
            $conversation->language_preferred ?? $request->user()?->locale,
            $request->header('Accept-Language'),
            $request->user()?->locale ?? $conversation->contact?->language_preferred
        );

        $message = $service->addMessage($conversation, [
            'text' => $validated['body'],
            'language' => $resolvedLanguage['language'],
            'client_message_id' => $validated['client_message_id'],
            'delivery_state' => 'sent',
        ], $request, $request->user());

        $service->syncLanguageContext($conversation, $resolvedLanguage, $request->user(), 'employee', true);

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
}
