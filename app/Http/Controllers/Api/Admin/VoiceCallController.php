<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Deal;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoiceCallController extends Controller
{
    public function __construct(private LocationAccessService $locationAccess)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'nullable|string',
            'direction' => 'nullable|string',
            'outcome' => 'nullable|string',
            'campaign_id' => 'nullable|integer',
            // Lets entity detail pages (e.g. the Location detail page's
            // "Voice calls" tab) pull just the calls relevant to them,
            // reusing this same endpoint rather than a bespoke one per page.
            'location_id' => 'nullable|integer',
            'yacht_id' => 'nullable|integer',
            'seller_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $query = CallSession::with(['seller:id,name', 'yacht:id,boat_name', 'location:id,name', 'campaign:id,name'])
            ->orderByDesc('created_at');

        $query = $this->locationAccess->scopeQuery($query, $user, 'harbor_id');

        if (! empty($validated['provider'])) {
            $query->where('provider', $validated['provider']);
        }
        if (! empty($validated['direction'])) {
            $query->where('direction', $validated['direction']);
        }
        if (! empty($validated['outcome'])) {
            $query->where('outcome', $validated['outcome']);
        }
        if (! empty($validated['campaign_id'])) {
            $query->where('campaign_id', $validated['campaign_id']);
        }
        if (! empty($validated['location_id'])) {
            $query->where('harbor_id', $validated['location_id']);
        }
        if (! empty($validated['yacht_id'])) {
            $query->where('yacht_id', $validated['yacht_id']);
        }
        if (! empty($validated['seller_id'])) {
            $query->where('seller_id', $validated['seller_id']);
        }
        if (! empty($validated['deal_id'])) {
            $query->where('deal_id', $validated['deal_id']);
        }

        return response()->json($query->paginate($validated['per_page'] ?? 25));
    }

    public function show(Request $request, CallSession $callSession): JsonResponse
    {
        if (! $this->locationAccess->sharesLocation($request->user(), $callSession->harbor_id)) {
            abort(403, 'Forbidden');
        }

        $callSession->load(['seller:id,name', 'yacht:id,boat_name', 'location:id,name', 'campaign:id,name', 'transcripts']);

        return response()->json(['data' => $callSession]);
    }

    /**
     * Conversion/cost reporting (spec §18: "Cost per seller onboarding,
     * cost per viewing, cost per completed deal"). Cost-per-outcome is
     * total spend on calls that reported that outcome divided by how many
     * there were — the cleanest metric derivable from call data alone,
     * without needing to trace a call to a specific downstream booking/deal
     * record that may not exist for every outcome type.
     */
    public function analytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'nullable|integer',
            'location_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $user = $request->user();
        $base = CallSession::query();
        $base = $this->locationAccess->scopeQuery($base, $user, 'harbor_id');

        if (! empty($validated['campaign_id'])) {
            $base->where('campaign_id', $validated['campaign_id']);
        }
        if (! empty($validated['location_id'])) {
            $base->where('harbor_id', $validated['location_id']);
        }
        if (! empty($validated['from'])) {
            $base->where('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $base->where('created_at', '<=', $validated['to']);
        }

        $totalCalls = (clone $base)->count();
        $totalSpend = (float) (clone $base)->sum('cost_eur');

        $byOutcome = (clone $base)
            ->whereNotNull('outcome')
            ->select('outcome', DB::raw('COUNT(*) as calls'), DB::raw('SUM(cost_eur) as spend'))
            ->groupBy('outcome')
            ->get()
            ->map(fn ($row) => [
                'outcome' => $row->outcome,
                'calls' => (int) $row->calls,
                'spend' => (float) $row->spend,
                'cost_per_call' => $row->calls > 0 ? round(((float) $row->spend) / $row->calls, 2) : null,
            ]);

        $onboardingOutcomes = ['interested', 'seller_onboarding_started', 'seller_onboarding_link_requested'];
        $costPerSellerOnboarding = $this->costPerOutcomeGroup($base, $onboardingOutcomes);
        $costPerViewing = $this->costPerOutcomeGroup($base, ['viewing_requested']);

        $dealIdsOnCalls = (clone $base)->whereNotNull('deal_id')->pluck('deal_id')->unique()->values();
        $closedDealIds = Deal::whereIn('id', $dealIdsOnCalls)
            ->where('status', 'closed')
            ->pluck('id');
        $callsOnClosedDeals = (clone $base)->whereIn('deal_id', $closedDealIds)->get(['cost_eur', 'deal_id']);
        $costPerCompletedDeal = $closedDealIds->isEmpty()
            ? null
            : round(((float) $callsOnClosedDeals->sum('cost_eur')) / $closedDealIds->count(), 2);

        return response()->json([
            'total_calls' => $totalCalls,
            'total_spend_eur' => round($totalSpend, 2),
            'avg_cost_per_call_eur' => $totalCalls > 0 ? round($totalSpend / $totalCalls, 2) : null,
            'by_outcome' => $byOutcome,
            'cost_per_seller_onboarding_eur' => $costPerSellerOnboarding,
            'cost_per_viewing_eur' => $costPerViewing,
            'cost_per_completed_deal_eur' => $costPerCompletedDeal,
            'completed_deals_count' => $closedDealIds->count(),
        ]);
    }

    private function costPerOutcomeGroup(Builder $baseQuery, array $outcomes): ?float
    {
        $matching = (clone $baseQuery)->whereIn('outcome', $outcomes)->get(['cost_eur']);
        if ($matching->isEmpty()) {
            return null;
        }

        return round(((float) $matching->sum('cost_eur')) / $matching->count(), 2);
    }
}
