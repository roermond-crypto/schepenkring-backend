<?php

namespace App\Actions\TaskAutomationRule;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Repositories\TaskAutomationRuleRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class CreateTaskAutomationRuleAction
{
    public function __construct(
        private TaskAutomationRuleRepository $rules,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, array $data)
    {
        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $locationId = $data['location_id'] ?? null;
        if ($actor->isEmployee()) {
            if (! $locationId) {
                $locationId = $actor->locations()->value('locations.id');
            }

            if (! $locationId || ! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }

            if (! $this->permissions->hasLocationPermission($actor, 'tasks.automation', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        $templateIds = $data['template_ids'];
        unset($data['template_ids']);
        $data['location_id'] = $locationId;

        $rule = $this->rules->create($data, $templateIds);

        $this->security->log('task.automation_rule.create', RiskLevel::LOW, $actor, $rule, [], [
            'location_id' => $locationId,
            'snapshot_after' => $rule->toArray(),
        ]);

        return $rule;
    }
}
