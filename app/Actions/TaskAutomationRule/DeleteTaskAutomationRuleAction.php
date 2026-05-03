<?php

namespace App\Actions\TaskAutomationRule;

use App\Enums\RiskLevel;
use App\Models\TaskAutomationRule;
use App\Models\User;
use App\Repositories\TaskAutomationRuleRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteTaskAutomationRuleAction
{
    public function __construct(
        private TaskAutomationRuleRepository $rules,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, TaskAutomationRule $rule): void
    {
        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        if ($actor->isEmployee()) {
            $locationId = $rule->location_id;
            if (! $locationId || ! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }

            if (! $this->permissions->hasLocationPermission($actor, 'tasks.automation', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        $before = $rule->toArray();
        $locationId = $rule->location_id;

        $this->rules->delete($rule);

        $this->security->log('task.automation_rule.delete', RiskLevel::LOW, $actor, $rule, [], [
            'location_id' => $locationId,
            'snapshot_before' => $before,
        ]);
    }
}
