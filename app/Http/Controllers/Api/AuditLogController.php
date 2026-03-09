<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\AuditResourceType;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List audit logs with filters.
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user:id,name,email,avatar')
            ->orderByDesc('created_at');

        $auditableTypes = AuditResourceType::resolveMany(
            $request->input('auditable_type', $request->input('entity_type'))
        );
        if (count($auditableTypes) > 0) {
            $query->where(function ($builder) use ($auditableTypes) {
                $builder->whereIn('entity_type', $auditableTypes)
                    ->orWhereIn('target_type', $auditableTypes);
            });
        }

        $auditableId = $request->input('auditable_id', $request->input('entity_id'));
        if ($auditableId) {
            $query->where(function ($builder) use ($auditableId) {
                $builder->where('entity_id', $auditableId)
                    ->orWhere('target_id', $auditableId);
            });
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('actor_id', $request->user_id);
        }

        // Date range
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        return $query->paginate($request->input('per_page', 50));
    }

    /**
     * Get audit log for a specific resource.
     */
    public function forResource(Request $request, string $type, int $id)
    {
        $resolvedType = AuditResourceType::resolve($type) ?? $type;

        return AuditLog::with('user:id,name,email,avatar')
            ->forModel($resolvedType, $id)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 25));
    }
}
