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
            // Load ALL active location IDs from the location_user pivot so that
            // employees linked to multiple locations can see all their chats.
            // Only active assignments are returned — deactivated assignments
            // are excluded so a suspended salesguy loses access immediately.
            $ids = $user->activeLocations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();

            if (count($ids) > 0) {
                return $ids;
            }

            // Fallback: if the pivot is empty but a legacy location_id is set
            // (e.g. via a direct column), use that so nothing breaks.
            $fallback = $user->getAttributeValue('location_id')
                ?? $user->resolvedLocationId();

            return $fallback ? [(int) $fallback] : [];
        }

        if ($user->isClient() && $user->client_location_id) {
            return [(int) $user->client_location_id];
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
