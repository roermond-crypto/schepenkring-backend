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

class UpdateTaskAutomationRuleAction
{
    public function __construct(
        private TaskAutomationRuleRepository $rules,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, TaskAutomationRule $rule, array $data)
    {
        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $locationId = $data['location_id'] ?? $rule->location_id;
        if ($actor->isEmployee()) {
            if (! $locationId || ! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }

            if (! $this->permissions->hasLocationPermission($actor, 'tasks.automation', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        $before = $rule->toArray();
        $templateIds = $data['template_ids'] ?? null;
        unset($data['template_ids']);
        $data['location_id'] = $locationId;

        $updated = $this->rules->update($rule, $data, $templateIds);

        $this->security->log('task.automation_rule.update', RiskLevel::LOW, $actor, $updated, [
            'fields' => array_keys($data),
        ], [
            'location_id' => $locationId,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
