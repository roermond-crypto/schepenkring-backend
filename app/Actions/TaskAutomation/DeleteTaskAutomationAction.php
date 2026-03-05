<?php

namespace App\Actions\TaskAutomation;

use App\Enums\RiskLevel;
use App\Models\TaskAutomation;
use App\Models\User;
use App\Repositories\TaskAutomationRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteTaskAutomationAction
{
    public function __construct(
        private TaskAutomationRepository $automations,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, TaskAutomation $automation): void
    {
        if ($actor->isEmployee()) {
            $locationId = $automation->location_id;
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

        $before = $automation->toArray();
        $locationId = $automation->location_id;

        $this->automations->delete($automation);

        $this->security->log('task.automation.delete', RiskLevel::LOW, $actor, $automation, [], [
            'location_id' => $locationId,
            'snapshot_before' => $before,
        ]);
    }
}
