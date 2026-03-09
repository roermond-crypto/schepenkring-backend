<?php

namespace App\Http\Controllers\Api;

use App\Models\CopilotAuditEvent;
use App\Http\Controllers\Controller;
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
            'source' => 'nullable|string|max:40',
            'stage' => 'nullable|string|max:30',
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
        if (!empty($validated['stage'])) {
            $query->where('stage', $validated['stage']);
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
        return $user?->isAdmin() ?? false;
    }
}
