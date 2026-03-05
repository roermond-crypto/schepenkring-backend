<?php

namespace App\Events;

class UserRegistered extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'user_registered';
    }
}
