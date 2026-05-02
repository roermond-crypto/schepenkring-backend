<?php

namespace App\Actions\TaskAutomationRule;

use App\Models\User;
use App\Repositories\TaskAutomationRuleRepository;
use App\Services\PermissionService;
use Illuminate\Support\Collection;

class ListTaskAutomationRulesAction
{
    public function __construct(
        private TaskAutomationRuleRepository $rules,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, array $filters = []): Collection
    {
        $query = $this->rules->query();

        if ($actor->isAdmin()) {
            if (! empty($filters['location_id'])) {
                $query->where('location_id', $filters['location_id']);
            }
        } else {
            $locationIds = $this->permissions->locationIdsForPermission($actor, 'tasks.automation');
            if (count($locationIds) === 0) {
                return collect();
            }

            $query->where(function ($query) use ($locationIds) {
                $query->whereNull('location_id')->orWhereIn('location_id', $locationIds);
            });
        }

        if (! empty($filters['trigger_event'])) {
            $query->where('trigger_event', $filters['trigger_event']);
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('id', 'desc')->get();
    }
}
