<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TaskAccessService
{
    public function __construct(
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function scopeTasksForUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isEmployee()) {
            $managerLocations = $this->permissions->locationIdsForPermission($user, 'tasks.manage');
            $locationIds = $this->locationAccess->accessibleLocationIds($user);

            if (count($locationIds) === 0) {
                return $query->whereRaw('1 = 0');
            }

            if (count($managerLocations) > 0) {
                return $query->where(function (Builder $builder) use ($managerLocations, $locationIds, $user) {
                    $builder->whereIn('location_id', $managerLocations)
                        ->orWhere(function (Builder $scoped) use ($locationIds, $user) {
                            $scoped->whereIn('location_id', $locationIds)
                                ->where(function (Builder $inner) use ($user) {
                                    $inner->where('assigned_to', $user->id)
                                        ->orWhere('user_id', $user->id)
                                        ->orWhere('created_by', $user->id);
                                });
                        });
                });
            }

            return $query
                ->whereIn('location_id', $locationIds)
                ->where(function (Builder $builder) use ($user) {
                    $builder->where('assigned_to', $user->id)
                        ->orWhere('user_id', $user->id)
                        ->orWhere('created_by', $user->id);
                });
        }

        if ($user->isClient()) {
            if (! $user->client_location_id) {
                return $query->whereRaw('1 = 0');
            }

            return $query
                ->where('location_id', $user->client_location_id)
                ->where('client_visible', true)
                ->where(function (Builder $builder) use ($user) {
                    $builder->where('assigned_to', $user->id)
                        ->orWhere('user_id', $user->id)
                        ->orWhere('created_by', $user->id);
                });
        }

        return $query->whereRaw('1 = 0');
    }

    public function canView(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isEmployee()) {
            if (! $this->locationAccess->sharesLocation($user, $task->location_id)) {
                return false;
            }

            if ($this->permissions->hasLocationPermission($user, 'tasks.manage', $task->location_id)) {
                return true;
            }

            return $task->assigned_to === $user->id
                || $task->user_id === $user->id
                || $task->created_by === $user->id;
        }

        if ($user->isClient()) {
            return $task->location_id === $user->client_location_id
                && $task->client_visible
                && (
                    $task->assigned_to === $user->id
                    || $task->user_id === $user->id
                    || $task->created_by === $user->id
                );
        }

        return false;
    }

    public function canEdit(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isEmployee()) {
            if (! $this->locationAccess->sharesLocation($user, $task->location_id)) {
                return false;
            }

            if ($this->permissions->hasLocationPermission($user, 'tasks.manage', $task->location_id)) {
                return true;
            }

            return $task->assigned_to === $user->id || $task->user_id === $user->id;
        }

        if ($user->isClient()) {
            return $this->canView($user, $task);
        }

        return false;
    }

    public function canDelete(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isEmployee()) {
            if (! $this->locationAccess->sharesLocation($user, $task->location_id)) {
                return false;
            }

            if ($this->permissions->hasLocationPermission($user, 'tasks.delete', $task->location_id)) {
                return true;
            }

            return $task->assigned_to === $user->id || $task->user_id === $user->id;
        }

        if ($user->isClient()) {
            return false;
        }

        return false;
    }

    public function canAssign(User $user, int $locationId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isEmployee()) {
            return false;
        }

        return $this->permissions->hasLocationPermission($user, 'tasks.assign', $locationId);
    }

    public function canManageAutomation(User $user, int $locationId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isEmployee()) {
            return false;
        }

        return $this->permissions->hasLocationPermission($user, 'tasks.automation', $locationId);
    }
}
