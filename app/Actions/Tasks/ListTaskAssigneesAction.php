<?php

namespace App\Actions\Tasks;

use App\Models\User;
use App\Services\LocationAccessService;
use Illuminate\Support\Collection;

class ListTaskAssigneesAction
{
    public function __construct(private LocationAccessService $locationAccess)
    {
    }

    public function execute(User $actor): Collection
    {
        if ($actor->isClient()) {
            return collect();
        }

        if ($actor->isAdmin()) {
            return User::query()
                ->where('status', 'ACTIVE')
                ->whereIn('type', ['ADMIN', 'EMPLOYEE'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'type'])
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->type,
                ]);
        }

        $locationIds = $this->locationAccess->accessibleLocationIds($actor);

        $employees = User::query()
            ->where('status', 'ACTIVE')
            ->where('type', 'EMPLOYEE')
            ->whereHas('locations', function ($query) use ($locationIds) {
                $query->whereIn('locations.id', $locationIds);
            })
            ->get(['id', 'name', 'email', 'type']);

        $admins = User::query()
            ->where('status', 'ACTIVE')
            ->where('type', 'ADMIN')
            ->get(['id', 'name', 'email', 'type']);

        return $admins->merge($employees)->sortBy('name')->values()->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->type,
        ]);
    }
}
