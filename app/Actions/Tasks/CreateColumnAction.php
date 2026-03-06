<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Board;
use App\Models\User;
use App\Repositories\ColumnRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class CreateColumnAction
{
    public function __construct(
        private ColumnRepository $columns,
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, Board $board, array $data)
    {
        $locationId = $board->location_id;

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

        $payload = $data;
        $payload['location_id'] = $locationId;

        $column = $this->columns->create($payload);

        $this->security->log('task.column.create', RiskLevel::LOW, $actor, $column, [], [
            'location_id' => $locationId,
            'snapshot_after' => $column->toArray(),
        ]);

        return $column;
    }
}
