<?php

namespace App\Events;

class ContractSigned extends AutomationEvent
{
    public function triggerName(): string
    {
        return 'contract_signed';
    }
}
