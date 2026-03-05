<?php

namespace App\Actions\Tasks;

use App\Enums\RiskLevel;
use App\Models\Board;
use App\Models\Column;
use App\Models\User;
use App\Repositories\BoardRepository;
use App\Services\ActionSecurity;
use App\Services\LocationAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class GetBoardAction
{
    public function __construct(
        private BoardRepository $boards,
        private LocationAccessService $locationAccess,
        private ActionSecurity $security
    ) {
    }

    public function execute(User $actor, ?int $locationId = null): Board
    {
        $resolvedLocationId = $this->resolveLocationId($actor, $locationId);

        if ($actor->isEmployee() && $resolvedLocationId && ! $this->locationAccess->sharesLocation($actor, $resolvedLocationId)) {
            throw new AuthorizationException('Unauthorized');
        }

        if ($actor->isClient() && $resolvedLocationId !== $actor->client_location_id) {
            throw new AuthorizationException('Unauthorized');
        }

        $board = $this->boards->firstByLocation($resolvedLocationId);

        if (! $board) {
            $board = $this->boards->create([
                'name' => 'Main Board',
                'owner_id' => $actor->id,
                'location_id' => $resolvedLocationId,
            ]);

            Column::insert([
                ['board_id' => $board->id, 'name' => 'To Do', 'position' => 0, 'location_id' => $resolvedLocationId],
                ['board_id' => $board->id, 'name' => 'In Progress', 'position' => 1, 'location_id' => $resolvedLocationId],
                ['board_id' => $board->id, 'name' => 'Done', 'position' => 2, 'location_id' => $resolvedLocationId],
            ]);

            $board->load(['columns' => function ($query) {
                $query->orderBy('position');
            }]);

            $this->security->log('task.board.create', RiskLevel::LOW, $actor, $board, [], [
                'location_id' => $resolvedLocationId,
                'snapshot_after' => $board->toArray(),
            ]);
        }

        return $board;
    }

    private function resolveLocationId(User $actor, ?int $locationId): ?int
    {
        if ($locationId) {
            return $locationId;
        }

        if ($actor->isClient()) {
            return $actor->client_location_id;
        }

        if ($actor->isEmployee()) {
            return $actor->locations()->value('locations.id');
        }

        return null;
    }
}
