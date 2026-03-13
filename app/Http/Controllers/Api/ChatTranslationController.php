<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatAbuseService;
use App\Services\ChatAccessService;
use App\Services\ChatTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ChatTranslationController extends Controller
{
    public function translate(
        Request $request,
        ChatTranslationService $translations,
        ChatAccessService $access
    ) {
        $payload = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
            'target_language' => ['required', 'string', 'max:32'],
            'source_language' => ['nullable', 'string', 'max:32'],
            'conversation_id' => ['nullable', 'string', 'exists:conversations,id'],
        ]);

        if (! empty($payload['conversation_id'])) {
            $conversation = Conversation::findOrFail($payload['conversation_id']);
            if (! $access->canAccessConversation($request->user(), $conversation)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        try {
            $translation = $translations->translate(
                $payload['text'],
                $payload['target_language'],
                $payload['source_language'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => 'Chat translation is unavailable.'], 503);
        }

        return $this->translationResponse($translation, $payload['conversation_id'] ?? null);
    }

    public function translatePublic(
        Request $request,
        ChatTranslationService $translations,
        ChatAbuseService $abuse
    ) {
        $payload = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
            'target_language' => ['required', 'string', 'max:32'],
            'source_language' => ['nullable', 'string', 'max:32'],
            'conversation_id' => ['nullable', 'string', 'exists:conversations,id'],
            'visitor_id' => ['nullable', 'string', 'max:64', 'required_without:session_jwt'],
            'session_jwt' => ['nullable', 'string', 'required_without:visitor_id'],
        ]);

        $visitorId = $payload['visitor_id'] ?? null;
        if (! empty($payload['session_jwt'])) {
            try {
                $decoded = json_decode(Crypt::decryptString($payload['session_jwt']), true, 512, JSON_THROW_ON_ERROR);
                $visitorId = $decoded['visitor_id'] ?? $visitorId;
            } catch (\Throwable $exception) {
                return response()->json(['message' => 'Invalid session token'], 401);
            }
        }

        $abuse->rateLimit($request, $visitorId, null);

        if (! empty($payload['conversation_id'])) {
            $conversation = Conversation::findOrFail($payload['conversation_id']);
            if ($conversation->visitor_id && $visitorId && $conversation->visitor_id !== $visitorId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if ($conversation->visitor_id && ! $visitorId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        try {
            $translation = $translations->translate(
                $payload['text'],
                $payload['target_language'],
                $payload['source_language'] ?? null
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['message' => 'Chat translation is unavailable.'], 503);
        }

        return $this->translationResponse($translation, $payload['conversation_id'] ?? null);
    }

    private function translationResponse(array $translation, ?string $conversationId = null)
    {
        return response()
            ->json(array_filter([
                'conversation_id' => $conversationId,
                'original_text' => $translation['original_text'] ?? null,
                'translated_text' => $translation['translated_text'] ?? null,
                'source_language' => $translation['source_language'] ?? null,
                'target_language' => $translation['target_language'] ?? null,
                'provider' => $translation['provider'] ?? null,
                'model' => $translation['model'] ?? null,
            ], static fn ($value) => $value !== null))
            ->header('Content-Language', (string) ($translation['target_language'] ?? 'en'))
            ->header('X-Source-Language', (string) ($translation['source_language'] ?? 'en'));
    }
}
