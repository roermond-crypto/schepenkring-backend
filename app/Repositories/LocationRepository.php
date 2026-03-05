<?php

namespace App\Repositories;

use App\Models\Location;

class LocationRepository
{
    public function findOrFail(int $id): Location
    {
        return Location::findOrFail($id);
    }
}
