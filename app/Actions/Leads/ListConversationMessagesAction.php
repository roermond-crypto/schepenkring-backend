<?php

namespace App\Actions\Leads;

use App\Models\User;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListConversationMessagesAction
{
    public function __construct(
        private ConversationRepository $conversations,
        private MessageRepository $messages
    ) {
    }

    public function execute(User $actor, string $conversationId, int $perPage = 50): LengthAwarePaginator
    {
        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $conversation = $this->conversations->findForUserOrFail($conversationId, $actor);

        return $this->messages
            ->queryForConversation($conversation->id)
            ->paginate($perPage);
    }
}
