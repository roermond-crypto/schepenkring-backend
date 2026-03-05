<?php

namespace App\Events;

class EscrowFunded extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'escrow_funded';
    }
}
