<?php

namespace App\Repositories;

use App\Models\ImpersonationSession;

class ImpersonationSessionRepository
{
    public function create(array $data): ImpersonationSession
    {
        return ImpersonationSession::create($data);
    }

    public function findActiveByTokenId(int $tokenId): ?ImpersonationSession
    {
        return ImpersonationSession::where('token_id', $tokenId)
            ->whereNull('ended_at')
            ->first();
    }
}
