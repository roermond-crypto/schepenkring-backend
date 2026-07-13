<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\AuditResourceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /**
     * GET /api/me/audit
     *
     * The account page's own Audit tab. Strictly scoped to the
     * authenticated user — actor_id = auth user OR the auth user is the
     * subject of the event (target_id/entity_type or entity_id/entity_type
     * resolving to User; both column pairs are checked since different
     * write paths in this app populate one or the other). The user id is
     * always taken from the authenticated session, never from client
     * input, so this can never be used to view another account's events.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $userTypes = AuditResourceType::resolveMany('user');

        $query = AuditLog::with('actor:id,name,email,avatar,type')
            ->where(function ($builder) use ($userId, $userTypes) {
                $builder->where('actor_id', $userId)
                    ->orWhere(function ($subject) use ($userId, $userTypes) {
                        $subject->where('target_id', $userId)->whereIn('target_type', $userTypes);
                    })
                    ->orWhere(function ($subject) use ($userId, $userTypes) {
                        $subject->where('entity_id', $userId)->whereIn('entity_type', $userTypes);
                    });
            })
            ->orderByDesc('created_at');

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        if ($request->boolean('errors_only')) {
            // Result values are stored inconsistently across write paths
            // in this app ('SUCCESS'/'success' vs 'FAIL'/'failure') —
            // a case-insensitive "not success" check catches both.
            $query->whereRaw('LOWER(result) != ?', ['success']);
        }

        $logs = $query->paginate((int) $request->input('per_page', 50));

        $logs->getCollection()->transform(function (AuditLog $log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'result' => $log->result,
                'created_at' => $log->created_at,
                'method' => $log->meta['method'] ?? null,
                'endpoint' => $log->meta['path'] ?? null,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                    'type' => $log->actor->type,
                ] : null,
            ];
        });

        return response()->json($logs);
    }
}
