<?php

namespace App\Actions\User;

use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUsersAction
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->users->queryWithFilters($filters)->paginate($perPage);
    }
}
