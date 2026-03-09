<?php

namespace App\Repositories;

use App\Models\AuditLog;
use App\Support\AuditResourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class AuditLogRepository
{
    public function query(): Builder
    {
        return AuditLog::query()->with(['actor', 'impersonator', 'location']);
    }

    public function findOrFail(int $id): AuditLog
    {
        return $this->query()->findOrFail($id);
    }

    public function queryWithFilters(array $filters): Builder
    {
        $query = $this->query();

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['user_id'])) {
            $userId = (int) $filters['user_id'];
            $query->where(function (Builder $builder) use ($userId) {
                $builder->where('actor_id', $userId)
                    ->orWhere('impersonator_id', $userId);
            });
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', (int) $filters['location_id']);
        }

        if (! empty($filters['action'])) {
            $actions = $this->normalizeList($filters['action']);
            if (count($actions) > 0) {
                $query->whereIn('action', $actions);
            }
        }

        if (! empty($filters['entity_type'])) {
            $types = AuditResourceType::resolveMany($filters['entity_type']);
            if (count($types) > 0) {
                $query->where(function (Builder $builder) use ($types) {
                    $builder->whereIn('entity_type', $types)
                        ->orWhereIn('target_type', $types);
                });
            }
        }

        if (! empty($filters['entity_id'])) {
            $entityId = (int) $filters['entity_id'];
            $query->where(function (Builder $builder) use ($entityId) {
                $builder->where('entity_id', $entityId)
                    ->orWhere('target_id', $entityId);
            });
        }

        if (! empty($filters['risk_level'])) {
            $riskLevels = array_map(fn ($level) => $level === 'MED' ? 'MEDIUM' : $level, $this->normalizeList($filters['risk_level']));
            if (count($riskLevels) > 0) {
                $query->whereIn('risk_level', $riskLevels);
            }
        }

        if (! empty($filters['result'])) {
            $results = $this->normalizeList($filters['result']);
            if (count($results) > 0) {
                $query->whereIn('result', $results);
            }
        }

        $search = $filters['search'] ?? $filters['query'] ?? null;
        if (! empty($search)) {
            $search = mb_strtolower($search);
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like) {
                $builder->whereRaw('LOWER(action) like ?', [$like])
                    ->orWhereRaw('LOWER(entity_type) like ?', [$like])
                    ->orWhereRaw('LOWER(target_type) like ?', [$like])
                    ->orWhereRaw('CAST(entity_id AS CHAR) like ?', [$like])
                    ->orWhereRaw('CAST(target_id AS CHAR) like ?', [$like])
                    ->orWhereRaw('LOWER(ip_address) like ?', [$like])
                    ->orWhereRaw('LOWER(ip_hash) like ?', [$like])
                    ->orWhereRaw('LOWER(user_agent) like ?', [$like])
                    ->orWhereRaw('LOWER(device_id) like ?', [$like])
                    ->orWhereRaw('LOWER(request_id) like ?', [$like])
                    ->orWhereRaw('LOWER(idempotency_key) like ?', [$like])
                    ->orWhereHas('actor', function (Builder $userQuery) use ($like) {
                        $userQuery->whereRaw('LOWER(name) like ?', [$like])
                            ->orWhereRaw('LOWER(email) like ?', [$like]);
                    })
                    ->orWhereHas('impersonator', function (Builder $userQuery) use ($like) {
                        $userQuery->whereRaw('LOWER(name) like ?', [$like])
                            ->orWhereRaw('LOWER(email) like ?', [$like]);
                    });
            });
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        return array_values(array_filter(array_map('strval', Arr::wrap($value))));
    }
}
