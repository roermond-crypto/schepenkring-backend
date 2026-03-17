<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    public function __construct(private LocationAccessService $locations)
    {
    }

    public function hasLocationPermission(User $user, string $permission, ?int $locationId = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isEmployee()) {
            return false;
        }

        $roles = $this->rolesForUser($user);

        if ($locationId !== null) {
            $role = $roles->get($locationId);

            return $this->roleHasPermission($role, $permission);
        }

        foreach ($roles as $role) {
            if ($this->roleHasPermission($role, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(User $user, string $role, ?int $locationId = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isEmployee()) {
            return false;
        }

        $roles = $this->rolesForUser($user);

        if ($locationId !== null) {
            return $roles->get($locationId) === $role;
        }

        return $roles->contains($role);
    }

    /**
     * @return array<int>
     */
    public function locationIdsForPermission(User $user, string $permission): array
    {
        if ($user->isAdmin()) {
            return [];
        }

        if (! $user->isEmployee()) {
            return [];
        }

        $roles = $this->rolesForUser($user);

        $locations = [];
        foreach ($roles as $locationId => $role) {
            if ($this->roleHasPermission($role, $permission)) {
                $locations[] = (int) $locationId;
            }
        }

        return $locations;
    }

    private function roleHasPermission(?string $role, string $permission): bool
    {
        if (! $role) {
            return false;
        }

        $permissions = config('permissions.roles.'.$role, []);

        return in_array($permission, $permissions, true);
    }

    private function rolesForUser(User $user)
    {
        $roles = $user->relationLoaded('locations')
            ? $user->locations->mapWithKeys(fn ($location) => [(int) $location->id => $location->pivot?->role])
            : $user->locations()->pluck('role', 'locations.id');

        if ($user->isAdmin()) {
            return collect($roles);
        }

        return collect($roles)->only($this->locations->accessibleLocationIds($user));
    }
}
