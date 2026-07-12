<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignTarget;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $campaigns = Campaign::withCount('targets')
            ->with('location:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $campaigns]);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $campaign->loadCount('targets');
        $campaign->load(['targets' => fn ($q) => $q->orderByDesc('score')->limit(100)]);

        return response()->json(['data' => $campaign]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $validated['created_by_user_id'] = $request->user()->id;
        $campaign = Campaign::create($validated);

        return response()->json(['data' => $campaign], 201);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $campaign->update($this->validated($request));

        return response()->json(['data' => $campaign]);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $campaign->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Adds leads matching a simple filter as pending targets — the "build
     * an audience" step spec §2 assumes exists. Kept intentionally simple
     * (status + location match) rather than a full segment-builder query
     * language, which nothing in this codebase has ever needed before.
     */
    public function addTargets(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'lead_status' => 'nullable|string',
            'location_id' => 'nullable|integer',
            'lead_ids' => 'nullable|array',
            'lead_ids.*' => 'integer',
        ]);

        $query = Lead::query();
        if (! empty($validated['lead_ids'])) {
            $query->whereIn('id', $validated['lead_ids']);
        } else {
            if (! empty($validated['lead_status'])) {
                $query->where('status', $validated['lead_status']);
            }
            if (! empty($validated['location_id'])) {
                $query->where('location_id', $validated['location_id']);
            }
        }

        $existingLeadIds = $campaign->targets()->where('target_type', 'lead')->pluck('target_id');
        $leads = $query->whereNotIn('id', $existingLeadIds)->limit(500)->get();

        foreach ($leads as $lead) {
            CampaignTarget::create([
                'campaign_id' => $campaign->id,
                'target_type' => 'lead',
                'target_id' => $lead->id,
                'status' => 'pending',
            ]);
        }

        return response()->json(['added' => $leads->count()]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'status' => 'nullable|string|in:draft,active,paused,completed',
            'location_id' => 'nullable|integer|exists:locations,id',
            'target_criteria' => 'nullable|array',
            'email_template_key' => 'nullable|string|max:255',
            'voice_agent_id' => 'nullable|string|max:255',
            'calling_hours' => 'nullable|array',
            'max_call_attempts' => 'nullable|integer|min:1|max:20',
            'retry_delay_hours' => 'nullable|integer|min:1',
            'spend_cap_eur' => 'nullable|numeric|min:0',
            'min_score_to_call' => 'nullable|integer|min:0|max:100',
        ]);
    }
}
