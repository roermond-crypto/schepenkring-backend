<?php

namespace App\Events;

class BoatCreated extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'boat_created';
    }
}
