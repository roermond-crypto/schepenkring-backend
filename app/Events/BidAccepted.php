<?php

namespace App\Events;

class BidAccepted extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'bid_accepted';
    }
}
