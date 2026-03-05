<?php

namespace App\Actions\Tasks;

use App\Models\Column;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReorderColumnsAction
{
    public function __construct(
        private LocationAccessService $locationAccess,
        private PermissionService $permissions
    ) {
    }

    public function execute(User $actor, array $columns): void
    {
        if ($actor->isAdmin()) {
            $this->applyUpdates($columns);
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
}
