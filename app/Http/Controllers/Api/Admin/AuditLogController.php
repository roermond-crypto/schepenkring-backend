<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Audit\ListAuditLogsAction;
use App\Actions\Audit\ShowAuditLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminAuditIndexRequest;
use App\Http\Resources\AuditLogResource;
use Illuminate\Http\Request;

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
}
