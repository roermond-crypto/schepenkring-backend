<?php

namespace App\Repositories;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class UserRepository
{
    public function query(): Builder
    {
        return User::query()->with(['locations', 'clientLocation']);
    }

    public function findOrFail(int $id): User
    {
        return $this->query()->findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->fill($data);
        $user->save();

        return $user;
    }

    public function queryWithFilters(array $filters): Builder
    {
        $query = $this->query();

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.strtolower($filters['search']).'%';
            $query->where(function (Builder $builder) use ($search) {
                $builder->whereRaw('LOWER(name) like ?', [$search])
                    ->orWhereRaw('LOWER(email) like ?', [$search])
                    ->orWhereRaw('LOWER(phone) like ?', [$search]);
            });
        }

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];
            $query->where(function (Builder $builder) use ($locationId) {
                $builder->where('client_location_id', $locationId)
                    ->orWhereHas('locations', function (Builder $locationQuery) use ($locationId) {
                        $locationQuery->where('locations.id', $locationId);
                    });
            });
        }

        return $query;
    }

    public function queryClientsForUser(User $actor): Builder
    {
        $query = $this->query()->where('type', UserType::CLIENT->value);

        if ($actor->isAdmin()) {
            return $query;
        }

        if ($actor->isEmployee()) {
            $locationIds = $actor->locations()->pluck('locations.id')->all();

            if (count($locationIds) === 0) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn('client_location_id', $locationIds);
        }

        return $query->where('id', $actor->id);
    }

    public function syncLocations(User $user, array $locations): void
    {
        $syncData = [];

        foreach ($locations as $location) {
            if (! isset($location['location_id'], $location['role'])) {
                continue;
            }

            $syncData[$location['location_id']] = [
                'role' => $location['role'],
            ];
        }

        $user->locations()->sync($syncData);
    }

    public function revokeTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    public function sanitizeAttributes(array $data, array $allowed): array
    {
        return Arr::only($data, $allowed);
    }
}
