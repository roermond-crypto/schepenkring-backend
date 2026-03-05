<?php

namespace App\Http\Controllers;

use App\Models\CopilotAuditEvent;
use Illuminate\Http\Request;

class CopilotAuditController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'source' => 'nullable|string|in:header,chatpage,voice',
            'status' => 'nullable|string|max:30',
            'failed_only' => 'nullable|boolean',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'all' => 'nullable|boolean',
        ]);

        $query = CopilotAuditEvent::query();
        if (!$this->canViewAll($user) || empty($validated['all'])) {
            $query->where('user_id', $user->id);
        }

        if (!empty($validated['source'])) {
            $query->where('source', $validated['source']);
        }
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['failed_only'])) {
            $query->whereNotNull('failure_reason');
        }
        if (!empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $perPage = $validated['per_page'] ?? 25;

        return response()->json($query->orderByDesc('id')->paginate($perPage));
    }

    private function canViewAll($user): bool
    {
        $role = strtolower((string) $user->role);
        return $role === 'admin' || $role === 'superadmin';
    }
}
