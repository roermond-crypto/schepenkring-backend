<?php

namespace App\Actions\TaskAutomation;

use App\Models\User;
use App\Repositories\TaskAutomationRepository;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Support\Collection;

class ListTaskAutomationsAction
{
    public function __construct(
        private TaskAutomationRepository $automations,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, array $filters = []): Collection
    {
        $query = $this->automations->query();

        if ($actor->isAdmin()) {
            if (! empty($filters['location_id'])) {
                $query->where('location_id', $filters['location_id']);
            }
        } else {
            $locationIds = $this->permissions->locationIdsForPermission($actor, 'tasks.automation');
            if (count($locationIds) === 0) {
                return collect();
            }
            $query->whereIn('location_id', $locationIds);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['trigger_event'])) {
            $query->where('trigger_event', $filters['trigger_event']);
        }

        if (! empty($filters['related_type'])) {
            $query->where('related_type', $filters['related_type']);
        }

        if (! empty($filters['related_id'])) {
            $query->where('related_id', $filters['related_id']);
        }

        return $query->orderBy('due_at')->get();
    }
}
