<?php

namespace App\Repositories;

use App\Models\Lead;
use App\Models\User;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;

class LeadRepository
{
    public function __construct(private LocationAccessService $locationAccess)
    {
    }

    public function queryForUser(User $user): Builder
    {
        $query = Lead::query()->with([
            'conversation',
            'location',
            'assignedEmployee',
            'convertedClient',
        ]);

        return $this->locationAccess->scopeQuery($query, $user, 'location_id');
    }

    public function findForUserOrFail(int $id, User $user): Lead
    {
        return $this->queryForUser($user)->findOrFail($id);
    }

    public function findByConversationId(string $conversationId): ?Lead
    {
        return Lead::query()
            ->with(['conversation', 'location', 'assignedEmployee', 'convertedClient'])
            ->where('conversation_id', $conversationId)
            ->first();
    }

    public function create(array $data): Lead
    {
        return Lead::create($data);
    }

    public function update(Lead $lead, array $data): Lead
    {
        $lead->fill($data);
        $lead->save();

        return $lead;
    }
}
