<?php

namespace App\Repositories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;

class MessageRepository
{
    public function queryForConversation(string $conversationId): Builder
    {
        return Message::query()
            ->with('employee')
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at');
    }

    public function findByClientMessageId(string $conversationId, string $clientMessageId): ?Message
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('client_message_id', $clientMessageId)
            ->first();
    }

    public function create(array $data): Message
    {
        return Message::create($data);
    }
}
