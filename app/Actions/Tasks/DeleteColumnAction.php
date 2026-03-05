<?php

namespace App\Actions\Tasks;

use App\Models\Column;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteColumnAction
{
    public function __construct(
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, Column $column): void
    {
        $locationId = $column->location_id;

        if ($actor->isEmployee()) {
            if ($locationId && ! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
            if ($locationId && ! $this->permissions->hasLocationPermission($actor, 'tasks.manage', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $column->delete();
    }
}
