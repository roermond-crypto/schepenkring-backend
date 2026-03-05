<?php

namespace App\Actions\Audit;

use App\Models\User;
use App\Repositories\AuditLogRepository;
use App\Services\LocationAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAuditLogsAction
{
    public function __construct(
        private AuditLogRepository $auditLogs,
        private LocationAccessService $locationAccess
    ) {
    }

    public function execute(User $actor, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 50);
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = $this->auditLogs->queryWithFilters($filters);
        $query = $this->locationAccess->scopeQuery($query, $actor, 'location_id');

        $allowedSorts = ['created_at', 'action', 'risk_level', 'result'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }
}
