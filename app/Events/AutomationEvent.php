<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class AutomationEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public mixed $entity,
        public ?User $actor = null
    ) {
    }

    abstract public function triggerName(): string;
}
