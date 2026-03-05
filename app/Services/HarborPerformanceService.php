<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Payment;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Facades\DB;

class HarborPerformanceService
{
    public function __construct(
        private Ga4DataApiService $ga4,
    ) {
    }

    public function buildReport(string $startDate, string $endDate, array $filters = []): array
    {
        $harbors = User::where('role', 'Partner')->where('status', 'Active')->orderBy('name')->get();
        $harborIds = $harbors->pluck('id')->all();

        $eventCounts = $this->ga4->fetchEventCounts([
            'harbor_button_impression',
            'harbor_button_click',
            'boat_form_started',
            'boat_submitted',
            'deal_completed',
            'session_start',
            'first_visit',
        ], $startDate, $endDate, $filters);

        $traffic = $this->ga4->fetchTrafficMetrics($startDate, $endDate, $filters);

        if (empty($traffic)) {
            $traffic = $this->buildTrafficFallback($eventCounts);
        }

        $dbMetrics = $this->fetchDbMetrics($startDate, $endDate, $harborIds);

        $rows = [];
        $ctrValues = [];
        $formStartRates = [];
        $submitRates = [];
        $dealRates = [];

        $commissionTotals = [];
        foreach ($harbors as $harbor) {
            $commissionTotals[$harbor->id] = (float) ($dbMetrics['commission'][$harbor->id] ?? 0);
        }

        arsort($commissionTotals);
        $rankings = [];
        $rank = 1;
        foreach (array_keys($commissionTotals) as $harborId) {
            $rankings[$harborId] = $rank;
            $rank++;
        }

        foreach ($harbors as $harbor) {
            $harborId = $harbor->id;
            $events = $eventCounts[$harborId] ?? [];
            $impressions = (int) ($events['harbor_button_impression'] ?? 0);
            $clicks = (int) ($events['harbor_button_click'] ?? 0);
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
            if ($impressions > 0) {
                $ctrValues[] = $ctr;
            }

            $formStarted = (int) ($events['boat_form_started'] ?? 0);
            $boatSubmitted = (int) ($events['boat_submitted'] ?? 0);
            $dealCompleted = (int) ($events['deal_completed'] ?? 0);

            $clickToForm = $clicks > 0 ? round(($formStarted / $clicks) * 100, 2) : 0.0;
            $formToSubmit = $formStarted > 0 ? round(($boatSubmitted / $formStarted) * 100, 2) : 0.0;
            $submitToDeal = $boatSubmitted > 0 ? round(($dealCompleted / $boatSubmitted) * 100, 2) : 0.0;

            if ($clicks > 0) {
                $formStartRates[] = $clickToForm;
            }
            if ($formStarted > 0) {
                $submitRates[] = $formToSubmit;
            }
            if ($boatSubmitted > 0) {
                $dealRates[] = $submitToDeal;
            }

            $rows[] = [
                'harbor' => [
                    'id' => $harborId,
                    'name' => $harbor->name,
                    'email' => $harbor->email,
                    'status' => $harbor->status,
                    'rank_by_commission' => $rankings[$harborId] ?? null,
                ],
                'ga4' => [
                    'active_users' => (int) ($traffic[$harborId]['active_users'] ?? 0),
                    'sessions' => (int) ($traffic[$harborId]['sessions'] ?? ($events['session_start'] ?? 0)),
                    'users' => (int) ($traffic[$harborId]['active_users'] ?? ($events['first_visit'] ?? 0)),
                    'button_impressions' => $impressions,
                    'button_clicks' => $clicks,
                    'ctr' => $ctr,
                    'boat_form_started' => $formStarted,
                    'boat_submitted' => $boatSubmitted,
                    'deal_completed' => $dealCompleted,
                    'funnel' => [
                        'click_to_form_rate' => $clickToForm,
                        'form_to_submit_rate' => $formToSubmit,
                        'submit_to_deal_rate' => $submitToDeal,
                    ],
                ],
                'db' => [
                    'boats_submitted' => (int) ($dbMetrics['boats'][$harborId] ?? 0),
                    'deals_completed' => (int) ($dbMetrics['deals'][$harborId] ?? 0),
                    'commission_total' => (float) ($dbMetrics['commission'][$harborId] ?? 0),
                ],
            ];
        }

        $benchmarkCtr = $ctrValues ? round(array_sum($ctrValues) / count($ctrValues), 2) : 0.0;
        $benchmarkFormStart = $formStartRates ? round(array_sum($formStartRates) / count($formStartRates), 2) : 0.0;
        $benchmarkSubmit = $submitRates ? round(array_sum($submitRates) / count($submitRates), 2) : 0.0;
        $benchmarkDeal = $dealRates ? round(array_sum($dealRates) / count($dealRates), 2) : 0.0;

        return [
            'range' => [
                'from' => $startDate,
                'to' => $endDate,
            ],
            'filters' => $filters,
            'benchmark' => [
                'avg_ctr' => $benchmarkCtr,
                'avg_click_to_form_rate' => $benchmarkFormStart,
                'avg_form_to_submit_rate' => $benchmarkSubmit,
                'avg_submit_to_deal_rate' => $benchmarkDeal,
            ],
            'harbors' => $rows,
        ];
    }

    private function fetchDbMetrics(string $startDate, string $endDate, array $harborIds): array
    {
        $rangeStart = $startDate . ' 00:00:00';
        $rangeEnd = $endDate . ' 23:59:59';

        $boats = Yacht::select('ref_harbor_id', DB::raw('count(*) as count'))
            ->whereNotNull('ref_harbor_id')
            ->whereIn('ref_harbor_id', $harborIds)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->groupBy('ref_harbor_id')
            ->pluck('count', 'ref_harbor_id')
            ->all();

        $deals = Deal::join('yachts', 'deals.boat_id', '=', 'yachts.id')
            ->where('deals.status', 'completed')
            ->whereNotNull('yachts.ref_harbor_id')
            ->whereBetween('deals.updated_at', [$rangeStart, $rangeEnd])
            ->whereIn('yachts.ref_harbor_id', $harborIds)
            ->select('yachts.ref_harbor_id', DB::raw('count(*) as count'))
            ->groupBy('yachts.ref_harbor_id')
            ->pluck('count', 'ref_harbor_id')
            ->all();

        $commission = Payment::join('deals', 'payments.deal_id', '=', 'deals.id')
            ->join('yachts', 'deals.boat_id', '=', 'yachts.id')
            ->where('payments.type', 'platform_fee')
            ->where('payments.status', 'paid')
            ->whereNotNull('yachts.ref_harbor_id')
            ->whereBetween('payments.updated_at', [$rangeStart, $rangeEnd])
            ->whereIn('yachts.ref_harbor_id', $harborIds)
            ->select('yachts.ref_harbor_id', DB::raw('sum(payments.amount_value) as total'))
            ->groupBy('yachts.ref_harbor_id')
            ->pluck('total', 'ref_harbor_id')
            ->all();

        return [
            'boats' => $boats,
            'deals' => $deals,
            'commission' => $commission,
        ];
    }

    private function buildTrafficFallback(array $eventCounts): array
    {
        $fallback = [];
        foreach ($eventCounts as $harborId => $events) {
            $fallback[$harborId] = [
                'active_users' => (int) ($events['first_visit'] ?? 0),
                'sessions' => (int) ($events['session_start'] ?? 0),
            ];
        }
        return $fallback;
    }
}
