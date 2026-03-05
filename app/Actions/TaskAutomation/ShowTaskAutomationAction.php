<?php

namespace App\Actions\TaskAutomation;

use App\Models\TaskAutomation;
use App\Models\User;
use App\Repositories\TaskAutomationRepository;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class ShowTaskAutomationAction
{
    public function __construct(
        private TaskAutomationRepository $automations,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, int $id): TaskAutomation
    {
        $automation = $this->automations->findOrFail($id);

        if ($actor->isAdmin()) {
            return $automation;
        }

        $locationId = $automation->location_id;
        if (! $locationId || ! $this->locationAccess->sharesLocation($actor, $locationId)) {
            throw new AuthorizationException('Unauthorized');
        }

        if (! $this->permissions->hasLocationPermission($actor, 'tasks.automation', $locationId)) {
            throw new AuthorizationException('Unauthorized');
        }

        return $automation;
    }
}
