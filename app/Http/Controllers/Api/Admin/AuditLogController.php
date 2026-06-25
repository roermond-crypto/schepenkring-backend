<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Audit\ListAuditLogsAction;
use App\Actions\Audit\ShowAuditLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminAuditIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\LocationAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(AdminAuditIndexRequest $request, ListAuditLogsAction $action)
    {
        $logs = $action->execute($request->user(), $request->validated());

        return AuditLogResource::collection($logs);
    }

    public function show(int $id, Request $request, ShowAuditLogAction $action)
    {
        $log = $action->execute($request->user(), $id);

        return response()->json([
            'data' => new AuditLogResource($log),
        ]);
    }

    public function summary(Request $request, LocationAccessService $locationAccess)
    {
        $baseQuery = AuditLog::query();
        $baseQuery = $locationAccess->scopeQuery($baseQuery, $request->user(), 'location_id');

        $eventTypes = (clone $baseQuery)
            ->select('action as event_type', DB::raw('COUNT(*) as count'))
            ->whereNotNull('action')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['event_type' => $row->event_type, 'count' => (int) $row->count]);

        $entityTypes = (clone $baseQuery)
            ->select(DB::raw('COALESCE(entity_type, target_type) as entity_type'), DB::raw('COUNT(*) as count'))
            ->where(function ($q) {
                $q->whereNotNull('entity_type')->orWhereNotNull('target_type');
            })
            ->groupBy(DB::raw('COALESCE(entity_type, target_type)'))
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['entity_type' => $row->entity_type, 'count' => (int) $row->count]);

        $recentCount = (clone $baseQuery)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $totalCount = (clone $baseQuery)->count();

        return response()->json([
            'event_types' => $eventTypes,
            'entity_types' => $entityTypes,
            'recent_activity_count' => $recentCount,
            'total_count' => $totalCount,
        ]);
    }
}
