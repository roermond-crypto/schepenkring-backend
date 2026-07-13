<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Yacht;
use App\Models\YachtInfoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YachtInfoRequestController extends Controller
{
    /**
     * POST /api/admin/yachts/{yacht}/info-requests
     */
    public function store(Request $request, Yacht $yacht): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|string|max:255',
        ]);

        $infoRequest = YachtInfoRequest::create([
            'yacht_id' => $yacht->id,
            'requested_by_id' => $request->user()->id,
            'items' => $validated['items'],
            'status' => 'open',
        ]);

        AuditLog::create([
            'action' => 'yacht.info_request.created',
            'risk_level' => 'low',
            'result' => 'success',
            'actor_id' => $request->user()?->id,
            'entity_type' => 'yacht',
            'entity_id' => $yacht->id,
            'meta' => ['info_request_id' => $infoRequest->id, 'items' => $validated['items']],
            'ip_address' => $request->ip(),
        ]);

        return response()->json($infoRequest, 201);
    }

    /**
     * GET /api/admin/yachts/{yacht}/info-requests
     */
    public function index(Yacht $yacht): JsonResponse
    {
        $requests = $yacht->infoRequests()
            ->with(['requestedBy:id,name', 'resolvedBy:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $requests]);
    }

    /**
     * POST /api/admin/info-requests/{infoRequest}/resolve
     */
    public function resolve(Request $request, YachtInfoRequest $infoRequest): JsonResponse
    {
        $infoRequest->update([
            'status' => 'resolved',
            'resolved_by_id' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        AuditLog::create([
            'action' => 'yacht.info_request.resolved',
            'risk_level' => 'low',
            'result' => 'success',
            'actor_id' => $request->user()?->id,
            'entity_type' => 'yacht',
            'entity_id' => $infoRequest->yacht_id,
            'meta' => ['info_request_id' => $infoRequest->id],
            'ip_address' => $request->ip(),
        ]);

        return response()->json($infoRequest->fresh());
    }
}
