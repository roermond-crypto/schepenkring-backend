<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Column;
use App\Models\User;
use App\Repositories\ColumnRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateColumnAction
{
    public function __construct(
        private ColumnRepository $columns,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, Column $column, array $data)
    {
        $locationId = $column->location_id;

        if ($actor->isEmployee()) {
            if ($locationId && ! $this->locationAccess->sharesLocation($actor, $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
            if ($locationId && ! $this->permissions->hasLocationPermission($actor, 'tasks.manage', $locationId)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $before = $column->toArray();
        $updated = $this->columns->update($column, $data);

        $this->security->log('task.column.update', RiskLevel::LOW, $actor, $updated, [
            'fields' => array_keys($data),
        ], [
            'location_id' => $updated->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->toArray(),
        ]);

        return $updated;
    }
}
