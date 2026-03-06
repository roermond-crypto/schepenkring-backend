<?php

namespace App\Repositories;

use App\Models\Lead;
use App\Models\User;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;

class LeadRepository
{
    public function __construct(private LocationAccessService $locationAccess)
    {
    }

    public function queryForUser(User $user): Builder
    {
        $query = Lead::query()->with(['location', 'client']);

        return $this->locationAccess->scopeQuery($query, $user, 'location_id');
    }
}
