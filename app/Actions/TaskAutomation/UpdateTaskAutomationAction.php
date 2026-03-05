<?php

namespace App\Actions\TaskAutomation;

use App\Models\TaskAutomation;
use App\Models\User;
use App\Repositories\TaskAutomationRepository;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateTaskAutomationAction
{
    public function __construct(
        private TaskAutomationRepository $automations,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, TaskAutomation $automation, array $data)
    {
        $locationId = $data['location_id'] ?? $automation->location_id;

        if ($actor->isEmployee()) {
            if (! $locationId || ! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
            if (! $this->permissions->hasLocationPermission($actor, 'tasks.automation', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $data['location_id'] = $locationId;

        return $this->automations->update($automation, $data)->fresh('template');
    }
}
