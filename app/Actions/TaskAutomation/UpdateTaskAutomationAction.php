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

class UpdateTaskAutomationAction
{
    public function __construct(
        private TaskAutomationRepository $automations,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
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

        $before = $automation->toArray();
        $data['location_id'] = $locationId;

        $updated = $this->automations->update($automation, $data)->fresh('template');

        $this->security->log('task.automation.update', RiskLevel::LOW, $actor, $updated, [
            'fields' => array_keys($data),
        ], [
            'location_id' => $locationId,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
