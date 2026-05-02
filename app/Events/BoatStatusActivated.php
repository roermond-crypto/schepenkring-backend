<?php

namespace App\Events;

use App\Models\Yacht;
use Illuminate\Foundation\Events\Dispatchable;

class BoatStatusActivated
{
    use Dispatchable;

    public function __construct(public readonly Yacht $entity) {}
}
