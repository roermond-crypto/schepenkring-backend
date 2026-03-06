<?php

namespace App\Actions\Leads;

use App\Models\User;
use App\Repositories\LeadRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListLeadsAction
{
    public function __construct(private LeadRepository $leads)
    {
    }

    public function execute(User $actor, array $filters = []): LengthAwarePaginator
    {
        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $query = $this->leads->queryForUser($actor);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', (int) $filters['location_id']);
        }

        if (! empty($filters['assigned_employee_id'])) {
            $query->where('assigned_employee_id', (int) $filters['assigned_employee_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
