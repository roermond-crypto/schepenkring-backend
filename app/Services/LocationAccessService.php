<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class LocationAccessService
{
    /**
     * @return array<int>
     */
    public function accessibleLocationIds(User $user): array
    {
        if ($user->isAdmin()) {
            return [];
        }

        if ($user->isEmployee()) {
            return $user->locations()->pluck('locations.id')->all();
        }

        if ($user->isClient() && $user->client_location_id) {
            return [$user->client_location_id];
        }

        return [];
    }

    public function scopeQuery(Builder $query, User $user, string $locationColumn = 'location_id'): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $locationIds = $this->accessibleLocationIds($user);

        if (count($locationIds) === 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($locationColumn, $locationIds);
    }

    public function sharesLocation(User $user, ?int $locationId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $locationId) {
            return false;
        }

        return in_array($locationId, $this->accessibleLocationIds($user), true);
    }
}
