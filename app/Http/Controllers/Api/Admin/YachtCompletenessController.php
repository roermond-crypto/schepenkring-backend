<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BoatFieldChange;
use App\Models\Yacht;
use App\Services\YachtCompletenessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YachtCompletenessController extends Controller
{
    public function __construct(private YachtCompletenessService $completeness) {}

    /**
     * GET /api/admin/yachts/{id}/score
     */
    public function show(Request $request, Yacht $yacht): JsonResponse
    {
        $breakdown = $this->completeness->get($yacht);

        return response()->json([
            'yacht_id'        => $yacht->id,
            'score'           => $yacht->completeness_score,
            'breakdown'       => $breakdown,
            'calculated_at'   => $yacht->completeness_calculated_at,
            'missing_required' => $this->completeness->missingRequired($yacht),
        ]);
    }

    /**
     * POST /api/admin/yachts/{id}/recalculate-score
     */
    public function recalculate(Request $request, Yacht $yacht): JsonResponse
    {
        $breakdown = $this->completeness->calculate($yacht);

        $this->logScoreEvent($yacht, $yacht->completeness_score, $request);

        return response()->json([
            'yacht_id'         => $yacht->id,
            'score'            => $yacht->fresh()->completeness_score,
            'breakdown'        => $breakdown,
            'calculated_at'    => now(),
            'missing_required' => $this->completeness->missingRequired($yacht),
        ]);
    }

    /**
     * GET /api/admin/yachts/{id}/audit
     * Combined audit: AuditLog entries + BoatFieldChange entries for this yacht.
     */
    public function audit(Request $request, Yacht $yacht): JsonResponse
    {
        $limit = min((int) $request->query('limit', 50), 200);

        // Structured audit events (publish, brochure import, score events, etc.)
        $auditLogs = AuditLog::where('entity_type', 'yacht')
            ->where('entity_id', $yacht->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($log) => [
                'type'       => 'audit_log',
                'id'         => $log->id,
                'action'     => $log->action,
                'result'     => $log->result,
                'actor_id'   => $log->actor_id,
                'meta'       => $log->meta,
                'created_at' => $log->created_at,
            ]);

        // Field-level changes
        $fieldChanges = BoatFieldChange::where('yacht_id', $yacht->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($fc) => [
                'type'         => 'field_change',
                'id'           => $fc->id,
                'field_name'   => $fc->field_name,
                'old_value'    => $fc->old_value,
                'new_value'    => $fc->new_value,
                'changed_by'   => $fc->changed_by_type,
                'actor_id'     => $fc->changed_by_id,
                'source_type'  => $fc->source_type,
                'reason'       => $fc->reason,
                'created_at'   => $fc->created_at,
            ]);

        // Merge and sort by date
        $combined = $auditLogs->concat($fieldChanges)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();

        return response()->json([
            'yacht_id' => $yacht->id,
            'data'     => $combined,
            'total'    => $combined->count(),
        ]);
    }

    /**
     * POST /api/admin/yachts/{id}/publish-yachtshift
     * Validates completeness before delegating to YachtShiftSyncService.
     */
    public function publish(Request $request, Yacht $yacht): JsonResponse
    {
        $missing = $this->completeness->missingRequired($yacht);

        if (!empty($missing) && !$request->boolean('force')) {
            return response()->json([
                'success'          => false,
                'message'          => 'Required fields are missing. Use force=true to override.',
                'missing_required' => $missing,
            ], 422);
        }

        // Delegate to existing YachtShiftSyncService
        /** @var \App\Services\YachtShiftSyncService $sync */
        $sync   = app(\App\Services\YachtShiftSyncService::class);
        $result = $sync->publishYacht($yacht, false, $request->user());

        // Recalculate score after publish (status might have changed)
        $this->completeness->calculate($yacht->fresh());

        return response()->json($result, ($result['success'] ?? false) ? 200 : 502);
    }

    // ── Private helpers ──────────────────────────────────────────

    private function logScoreEvent(Yacht $yacht, ?float $score, Request $request): void
    {
        try {
            AuditLog::create([
                'action'      => 'yacht.completeness_score_calculated',
                'risk_level'  => 'low',
                'result'      => 'success',
                'actor_id'    => $request->user()?->id,
                'location_id' => $yacht->location_id,
                'entity_type' => 'yacht',
                'entity_id'   => $yacht->id,
                'meta'        => [
                    'score'    => $score,
                    'yacht_id' => $yacht->id,
                ],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable) {
            // non-fatal
        }
    }
}
