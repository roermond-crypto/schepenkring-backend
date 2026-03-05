<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Column;
use App\Models\User;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReorderColumnsAction
{
    public function __construct(
        private LocationAccessService $locationAccess,
        private PermissionService $permissions,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, array $columns): void
    {
        if ($actor->isAdmin()) {
            $this->applyUpdates($columns);
            $this->logReorder($actor, $columns);
            return;
        }

        if (! $actor->isEmployee()) {
            throw new AuthorizationException('Unauthorized');
        }

        $locationIds = $this->locationAccess->accessibleLocationIds($actor);

        foreach ($columns as $columnData) {
            $column = Column::find($columnData['id']);
            if (! $column || ! in_array($column->location_id, $locationIds, true)) {
                throw new AuthorizationException('Unauthorized');
            }
            if (! $this->permissions->hasLocationPermission($actor, 'tasks.manage', $column->location_id)) {
                throw new AuthorizationException('Unauthorized');
            }
        }

        $this->applyUpdates($columns);
        $this->logReorder($actor, $columns);
    }

    private function applyUpdates(array $columns): void
    {
        DB::transaction(function () use ($columns) {
            foreach ($columns as $columnData) {
                Column::where('id', $columnData['id'])->update([
                    'position' => $columnData['position'],
                ]);
            }
        });
    }

    private function logReorder(User $actor, array $columns): void
    {
        $firstColumnId = $columns[0]['id'] ?? null;
        $firstColumn = $firstColumnId ? Column::find($firstColumnId) : null;

        $this->security->log('task.column.reorder', RiskLevel::LOW, $actor, $firstColumn, [
            'columns' => $columns,
        ], [
            'location_id' => $firstColumn?->location_id,
        ]);
    }
}
