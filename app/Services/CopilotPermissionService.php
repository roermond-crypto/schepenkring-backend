<?php

namespace App\Services;

use App\Models\User;

class CopilotPermissionService
{
    public function __construct(private PermissionService $permissions)
    {
    }

    public function canUseAction(User $user, ?string $permissionKey, ?string $requiredRole = null): bool
    {
        if ($requiredRole && ! $user->hasRole($requiredRole)) {
            return false;
        }

        if (! $permissionKey) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isEmployee()) {
            return false;
        }

        return $this->permissions->hasLocationPermission($user, $permissionKey);
    }
}
