<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatAccessService;
use App\Services\ChatConversationService;
use App\Support\CopilotLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ChatMessageController extends Controller
{
    public function store(
        Request $request,
        string $conversationId,
        ChatAccessService $access,
        ChatConversationService $service,
        CopilotLanguage $language
    )
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

            if (!$conversation->visitor_id && $visitorId) {
                $conversation->visitor_id = $visitorId;
                $conversation->save();
            }
        }

        $explicitLanguage = array_key_exists('language', $payload) && !empty($payload['language']);
        $resolvedLanguage = $language->resolve(
            (string) ($payload['text'] ?? ''),
            $payload['language'] ?? $payload['contact']['language_preferred'] ?? $conversation->language_preferred,
            $request->header('Accept-Language'),
            $user?->locale ?? $conversation->language_preferred ?? $conversation->contact?->language_preferred
        );
        $payload['language'] = $resolvedLanguage['language'];

        $message = $service->addMessage($conversation, $payload, $request, $user);
        $service->syncLanguageContext(
            $conversation,
            $resolvedLanguage,
            $user,
            $message->sender_type,
            !empty($payload['text']) || $explicitLanguage
        );

        return response()
            ->json(array_merge($message->toArray(), [
                'header_language' => $resolvedLanguage['header_language'],
                'language_detected_from_input' => $resolvedLanguage['detected_from_input'],
            ]), 201)
            ->header('Content-Language', $resolvedLanguage['language'])
            ->header('X-Header-Language', $resolvedLanguage['header_language']);
    }

    public function thumbsUp(Request $request, string $messageId)
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
