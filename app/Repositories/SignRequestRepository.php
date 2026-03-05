<?php

namespace App\Repositories;

use App\Models\SignRequest;
use App\Models\User;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;

class SignRequestRepository
{
    public function __construct(private LocationAccessService $locationAccess)
    {
    }

    public function queryForUser(User $user): Builder
    {
        $query = SignRequest::query()->with(['documents', 'requestedBy']);

        return $this->locationAccess->scopeQuery($query, $user, 'location_id');
    }

    public function findForUserOrFail(User $user, int $id): SignRequest
    {
        return $this->queryForUser($user)->findOrFail($id);
    }

    public function findLatestForEntity(User $user, string $entityType, int $entityId): ?SignRequest
    {
        return $this->queryForUser($user)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->latest()
            ->first();
    }

    public function create(array $data): SignRequest
    {
        return SignRequest::create($data);
    }

    public function update(SignRequest $request, array $data): SignRequest
    {
        $request->fill($data);
        $request->save();

        return $request;
    }
}
