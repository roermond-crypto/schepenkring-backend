<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\AuditLogRepository;
use App\Services\LocationAccessService;
use Illuminate\Auth\Access\AuthorizationException;

class ShowAuditLogAction
{
    public function __construct(
        private AuditLogRepository $auditLogs,
        private LocationAccessService $locationAccess
    ) {
    }

    public function execute(User $actor, int $id): AuditLog
    {
        $log = $this->auditLogs->findOrFail($id);

        if (! $actor->isAdmin() && ! $this->locationAccess->sharesLocation($actor, $log->location_id)) {
            throw new AuthorizationException('Unauthorized');
        }

        return $log;
    }
}
