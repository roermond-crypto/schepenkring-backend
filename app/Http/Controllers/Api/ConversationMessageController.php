<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
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
            'sender_type' => 'employee',
            'employee_id' => $request->user()->id,
            'body' => $validated['body'],
            'client_message_id' => $validated['client_message_id'],
            'delivery_state' => 'sent',
        ]);

        return response()->json(['message' => $message], 201);
    }
}
