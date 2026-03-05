<?php

namespace App\Policies;

use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\PermissionService;

class UserPolicy
{
    public function __construct(
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function view(User $actor, User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isClient()) {
            return $actor->id === $target->id;
        }

        if ($actor->isEmployee() && $target->isClient()) {
            return $this->locationAccess->sharesLocation($actor, $target->client_location_id);
        }

        return false;
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isClient()) {
            return $actor->id === $target->id;
        }

        if ($actor->isEmployee() && $target->isClient()) {
            return $this->locationAccess->sharesLocation($actor, $target->client_location_id);
        }

        return false;
    }

    public function impersonate(User $actor, User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if (! $actor->isEmployee() || ! $target->isClient()) {
            return false;
        }

        return $this->permissions->hasLocationPermission($actor, 'clients.impersonate', $target->client_location_id);
    }
}
