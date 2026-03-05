<?php

namespace App\Repositories;

use App\Models\Board;

class BoardRepository
{
    public function firstByLocation(?int $locationId): ?Board
    {
        return Board::with(['columns' => function ($query) {
            $query->orderBy('position');
        }])
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->orderBy('id')
            ->first();
    }

    public function create(array $data): Board
    {
        return Board::create($data);
    }
}
