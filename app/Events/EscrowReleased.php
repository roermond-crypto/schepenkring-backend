<?php

namespace App\Events;

class EscrowReleased extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'escrow_released';
    }
}
