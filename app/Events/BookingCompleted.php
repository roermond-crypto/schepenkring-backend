<?php

namespace App\Events;

class BookingCompleted extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'booking_completed';
    }
}
