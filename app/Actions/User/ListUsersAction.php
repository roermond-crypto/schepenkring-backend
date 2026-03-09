<?php

namespace App\Actions\User;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUsersAction
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $hasSearch = is_string($filters['search'] ?? null) && trim((string) $filters['search']) !== '';

        $query = $this->users->queryForActor($actor);
        $query = $this->users->queryWithFilters($filters, $query, ! $hasSearch);

        return $query->paginate($perPage);
    }
}
