<?php

namespace App\Services;

use App\Models\User;

class ImpersonationContext
{
    private ?User $impersonator = null;
    private ?int $sessionId = null;

    public function set(?User $impersonator, ?int $sessionId): void
    {
        $this->impersonator = $impersonator;
        $this->sessionId = $sessionId;
    }

    public function impersonator(): ?User
    {
        return $this->impersonator;
    }

    public function sessionId(): ?int
    {
        return $this->sessionId;
    }
}
