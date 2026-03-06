<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\User;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;

class ConversationRepository
{
    public function __construct(private LocationAccessService $locationAccess)
    {
    }

    public function queryForUser(User $user): Builder
    {
        $query = Conversation::query()->with(['lead', 'assignedEmployee', 'location']);

        return $this->locationAccess->scopeQuery($query, $user, 'location_id');
    }

    public function findForUserOrFail(string $id, User $user): Conversation
    {
        return $this->queryForUser($user)->findOrFail($id);
    }

    public function findPublicOrFail(string $id): Conversation
    {
        return Conversation::query()->with(['lead', 'assignedEmployee', 'location'])->findOrFail($id);
    }

    public function create(array $data): Conversation
    {
        return Conversation::create($data);
    }

    public function update(Conversation $conversation, array $data): Conversation
    {
        $conversation->fill($data);
        $conversation->save();

        return $conversation;
    }
}
