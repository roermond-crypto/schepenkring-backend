<?php

namespace App\Http\Controllers\Api;

use App\Actions\Leads\AddConversationMessageAction;
use App\Actions\Leads\ListConversationMessagesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Leads\ConversationMessageStoreRequest;
use App\Repositories\ConversationRepository;
use App\Services\IdempotencyService;
use Illuminate\Http\Request;

class ConversationMessageController extends Controller
{
    public function store(
        string $conversationId,
        ConversationMessageStoreRequest $request,
        ConversationRepository $conversations,
        AddConversationMessageAction $action,
        IdempotencyService $idempotency
    ) {
        $idempotencyResult = $idempotency->begin($request);
        if ($idempotencyResult['status'] === 'missing') {
            $idempotencyResult = null;
        } else {
            if ($idempotencyResult['status'] === 'conflict') {
                return response()->json(['message' => 'Idempotency-Key reuse with different request.'], 409);
            }
            if ($idempotencyResult['status'] === 'processing') {
                return response()->json(['message' => 'Request already in progress.'], 409);
            }
            if ($idempotencyResult['status'] === 'replay') {
                return $idempotencyResult['response'];
            }
        }

        $actor = $request->user();
        $conversation = $actor
            ? $conversations->findForUserOrFail($conversationId, $actor)
            : $conversations->findPublicOrFail($conversationId);

        $message = $action->execute($conversation, $request->validated(), $actor);

        $response = response()->json([
            'message' => $message,
        ], 201);

        if ($idempotencyResult && ! empty($idempotencyResult['record'])) {
            $idempotency->storeResponse($idempotencyResult['record'], $response);
        }

        return $response;
    }

    public function index(string $conversationId, Request $request, ListConversationMessagesAction $action)
    {
        $messages = $action->execute(
            $request->user(),
            $conversationId,
            (int) $request->query('per_page', 50)
        );

        return response()->json($messages);
    }
}
