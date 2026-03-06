<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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

        // Filter by auditable
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }
        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->auditable_id);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
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
        return AuditLog::with('user:id,name,email,avatar')
            ->forModel($type, $id)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 25));
    }
}
