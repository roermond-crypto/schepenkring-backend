<?php

namespace App\Events;

class HarborCreated extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'harbor_created';
    }
}
