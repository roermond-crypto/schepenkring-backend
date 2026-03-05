<?php

namespace App\Events;

class BookingCreated extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'booking_created';
    }
}
