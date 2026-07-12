<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\CampaignTarget;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActivityFeedService;
use App\Services\CampaignService;
use App\Services\FollowUpService;
use App\Services\LocationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backing endpoint for the Sales Command Center (spec §17) — aggregates
 * what already exists (FollowUp, CampaignTarget, CallSession) into one
 * "who do I need to call, and with what context" view. Deliberately does
 * NOT introduce any new source of truth; every row here links back to a
 * real record via its own detail endpoints.
 */
class SalesCommandCenterController extends Controller
{
    public function __construct(
        private LocationAccessService $locationAccess,
        private CampaignService $campaigns,
        private FollowUpService $followUps,
        private ActivityFeedService $activityFeed,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $locationIds = $this->locationAccess->accessibleLocationIds($user);
        $scoped = ! $user->isAdmin() && count($locationIds) > 0;

        return response()->json([
            'due_follow_ups' => $this->dueFollowUps($scoped, $locationIds),
            'prioritized_leads' => $this->prioritizedLeads($scoped, $locationIds),
            'recent_calls' => $this->recentCalls($scoped, $locationIds),
        ]);
    }

    private function dueFollowUps(bool $scoped, array $locationIds): array
    {
        $query = FollowUp::with(['assignedEmployee:id,name', 'relatedYacht:id,boat_name,ref_harbor_id,location_id'])
            ->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '<=', now()))
            ->orderBy('due_at');

        if ($scoped) {
            // FollowUp has no direct location column (it's polymorphic across
            // lead/user/campaign_target/call_session subjects) — scoped via
            // the related yacht where one exists. Follow-ups with no yacht
            // attached stay visible to all staff rather than being hidden,
            // since there's no reliable location to check them against.
            $query->where(function ($q) use ($locationIds) {
                $q->whereNull('related_yacht_id')
                    ->orWhereHas('relatedYacht', function ($yq) use ($locationIds) {
                        $yq->whereIn('ref_harbor_id', $locationIds)->orWhereIn('location_id', $locationIds);
                    });
            });
        }

        return $query->limit(50)->get()->map(fn (FollowUp $f) => [
            'id' => $f->id,
            'subject_type' => $f->subject_type,
            'subject_id' => $f->subject_id,
            'next_action' => $f->next_action,
            'due_at' => $f->due_at?->toIso8601String(),
            'last_outcome' => $f->last_outcome,
            'ai_summary' => $f->ai_summary,
            'assigned_employee' => $f->assignedEmployee?->name,
            'related_yacht' => $f->relatedYacht ? ['id' => $f->relatedYacht->id, 'boat_name' => $f->relatedYacht->boat_name] : null,
            'related_chat_thread_id' => $f->related_chat_thread_id,
        ])->all();
    }

    private function prioritizedLeads(bool $scoped, array $locationIds): array
    {
        $query = CampaignTarget::with('campaign:id,name')
            ->where('target_type', 'lead')
            ->where('status', 'scored')
            ->orderByDesc('score');

        if ($scoped) {
            $query->whereIn('target_id', Lead::whereIn('location_id', $locationIds)->pluck('id'));
        }

        return $query->limit(30)->get()->map(function (CampaignTarget $target) {
            $lead = Lead::find($target->target_id);

            return [
                'campaign_target_id' => $target->id,
                'campaign_name' => $target->campaign?->name,
                'score' => $target->score,
                'call_attempts' => $target->call_attempts,
                'lead_id' => $lead?->id,
                'name' => $lead?->name,
                'phone' => $lead?->phone,
                'yacht_id' => $lead?->yacht_id,
                'location_id' => $lead?->location_id,
            ];
        })->all();
    }

    private function recentCalls(bool $scoped, array $locationIds): array
    {
        $query = CallSession::with(['seller:id,name', 'yacht:id,boat_name'])
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at');

        if ($scoped) {
            $query->whereIn('harbor_id', $locationIds);
        }

        return $query->limit(30)->get()->map(fn (CallSession $call) => [
            'id' => $call->id,
            'provider' => $call->provider,
            'direction' => $call->direction,
            'outcome' => $call->outcome,
            'duration_seconds' => $call->duration_seconds,
            'ended_at' => $call->ended_at?->toIso8601String(),
            'summary' => data_get($call->metadata, 'ai_summary'),
            'seller' => $call->seller?->name,
            'yacht' => $call->yacht?->boat_name,
            'conversation_id' => $call->conversation_id,
        ])->all();
    }

    /**
     * "Call now" quick action — places an outbound voice call for a
     * CampaignTarget immediately, bypassing the scheduler's calling-hours/
     * score gating (a human explicitly decided to call, so those automated
     * guards don't apply). Reuses CampaignService::triggerCall() rather
     * than duplicating call-placement logic.
     */
    public function callNow(Request $request): JsonResponse
    {
        $validated = $request->validate(['campaign_target_id' => 'required|integer']);

        $target = CampaignTarget::findOrFail($validated['campaign_target_id']);
        $campaign = Campaign::findOrFail($target->campaign_id);

        $this->assertLocationAccess($request, $this->targetLocationId($target));

        $this->campaigns->triggerCall($target, $campaign);

        return response()->json(['triggered' => true]);
    }

    public function scheduleCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => 'required|string',
            'subject_id' => 'required|integer',
            'callback_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $followUp = $this->followUps->applyOutcome(
            $validated['subject_type'],
            $validated['subject_id'],
            'callback_requested',
            [
                'callback_at' => $validated['callback_at'],
                'ai_summary' => $validated['notes'] ?? null,
                'assigned_employee_id' => $request->user()->id,
            ],
        );

        return response()->json(['created' => true, 'follow_up_id' => $followUp?->id]);
    }

    public function markOutcome(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'follow_up_id' => 'required|integer',
            'status' => 'required|string|in:open,done,cancelled',
            'notes' => 'nullable|string',
        ]);

        $followUp = FollowUp::findOrFail($validated['follow_up_id']);
        $followUp->update([
            'status' => $validated['status'],
            'ai_summary' => $validated['notes'] ?? $followUp->ai_summary,
        ]);

        $this->activityFeed->record($followUp->subject_type, $followUp->subject_id, 'followup.outcome_marked', "Follow-up marked {$validated['status']} by staff", [
            'follow_up_id' => $followUp->id,
            'marked_by' => $request->user()->id,
        ], $request->user());

        return response()->json(['updated' => true]);
    }

    private function targetLocationId(CampaignTarget $target): ?int
    {
        return match ($target->target_type) {
            'lead' => Lead::find($target->target_id)?->location_id,
            'user' => User::find($target->target_id)?->client_location_id,
            default => null,
        };
    }

    private function assertLocationAccess(Request $request, ?int $locationId): void
    {
        $user = $request->user();
        if ($user->isAdmin() || $locationId === null) {
            return;
        }

        if (! $this->locationAccess->sharesLocation($user, $locationId)) {
            abort(403, 'Forbidden');
        }
    }
}
