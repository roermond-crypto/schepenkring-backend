<?php

namespace App\Events;

class DepositPaid extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'deposit_paid';
    }
}
