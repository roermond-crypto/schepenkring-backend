<?php

namespace App\Actions\TaskAutomationRule;

use App\Models\TaskAutomationRule;
use App\Models\User;
use App\Repositories\TaskAutomationRuleRepository;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class ShowTaskAutomationRuleAction
{
    public function __construct(
        private TaskAutomationRuleRepository $rules,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, int $id): TaskAutomationRule
    {
        $rule = $this->rules->findOrFail($id);

        if ($actor->isAdmin()) {
            return $rule;
        }

        $locationId = $rule->location_id;
        if ($locationId) {
            if (! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }

            if (! $this->permissions->hasLocationPermission($actor, 'tasks.automation', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }

            return $rule;
        }

        if (! $this->permissions->hasLocationPermission($actor, 'tasks.automation')) {
            throw new AuthorizationException('Unauthorized');
        }

        return $rule;
    }
}
