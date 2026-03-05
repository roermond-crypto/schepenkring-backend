<?php

namespace App\Events;

class BoatStatusActivated extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'boat_status_activated';
    }
}
