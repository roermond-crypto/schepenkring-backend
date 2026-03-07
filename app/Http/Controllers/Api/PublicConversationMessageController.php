<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Lead;
use Illuminate\Http\Request;

class PublicConversationMessageController extends Controller
{
    public function store($conversationId, Request $request)
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
            return response()->json(['message' => $existingMessage], 200);
        }

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_type' => 'visitor',
            'body' => $validated['body'],
            'client_message_id' => $validated['client_message_id'],
            'delivery_state' => 'sent',
        ]);

        return response()->json(['message' => $message], 201);
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
