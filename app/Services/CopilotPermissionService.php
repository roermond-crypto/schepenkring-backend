<?php

namespace App\Services;

use App\Models\User;

class CopilotPermissionService
{
    public function canUseAction(User $user, ?string $permissionKey): bool
    {
        if (!$permissionKey) {
            return true;
        }

        $role = strtolower((string) $user->role);
        if ($role === 'admin' || $role === 'superadmin') {
            return true;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            try {
                return $user->hasPermissionTo($permissionKey);
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }
}
