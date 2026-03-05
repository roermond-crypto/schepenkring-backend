<?php

namespace App\Repositories;

use App\Models\Boat;
use App\Models\User;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;

class BoatRepository
{
    public function __construct(private LocationAccessService $locationAccess)
    {
    }

    public function queryForUser(User $user): Builder
    {
        $query = Boat::query()->with(['location', 'client']);

        return $this->locationAccess->scopeQuery($query, $user, 'location_id');
    }
}
