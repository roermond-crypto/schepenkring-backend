<?php

namespace App\Services;

use App\Models\CallSession;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;

class PhoneBillingService
{
    public function computeCost(int $durationSeconds): array
    {
        $freeSeconds = (int) config('voice.free_seconds_threshold', 10);
        $costPerMinute = (float) config('voice.cost_per_minute_eur', 0.05);

        if ($durationSeconds <= 0) {
            return [
                'billable_seconds' => 0,
                'minutes' => 0,
                'cost' => 0.0,
            ];
        }

        if ($durationSeconds < $freeSeconds) {
            return [
                'billable_seconds' => 0,
                'minutes' => 0,
                'cost' => 0.0,
            ];
        }

        $minutes = (int) ceil($durationSeconds / 60);

        return [
            'billable_seconds' => $durationSeconds,
            'minutes' => $minutes,
            'cost' => round($minutes * $costPerMinute, 2),
        ];
    }

    public function chargeSessionsForDate(\Carbon\Carbon $date, ?int $harborId = null): int
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $query = CallSession::query()
            ->whereNotNull('ended_at')
            ->whereNull('charged_at')
            ->whereBetween('ended_at', [$start, $end]);

        if ($harborId) {
            $query->where('harbor_id', $harborId);
        }

        $sessions = $query->get()->groupBy('harbor_id');
        $chargedCount = 0;

        foreach ($sessions as $harborKey => $items) {
            if (!$harborKey) {
                continue;
            }

            DB::transaction(function () use ($harborKey, $items, $date, &$chargedCount) {
                $currency = config('wallet.default_currency', 'EUR');
                $referenceId = (int) $date->format('Ymd');
                $referenceKey = 'voice:' . $date->toDateString();

                $totalCost = 0.0;
                $totalMinutes = 0;
                $sessionIds = [];

                foreach ($items as $session) {
                    $sessionIds[] = $session->id;

                    $duration = (int) ($session->duration_seconds ?? 0);
                    $costData = $this->computeCost($duration);

                    $session->billable_seconds = $costData['billable_seconds'];
                    $session->cost_eur = $costData['cost'];
                    $session->save();

                    $totalCost += $costData['cost'];
                    $totalMinutes += $costData['minutes'];
                }

                $now = now();

                if ($totalCost > 0) {
                    WalletLedger::firstOrCreate(
                        [
                            'user_id' => (int) $harborKey,
                            'type' => WalletLedger::TYPE_VOICE_USAGE,
                            'reference_type' => 'voice_usage',
                            'reference_id' => $referenceId,
                            'reference_key' => $referenceKey,
                        ],
                        [
                            'amount' => -1 * abs($totalCost),
                            'currency' => $currency,
                            'metadata' => [
                                'date' => $date->toDateString(),
                                'session_count' => count($sessionIds),
                                'minutes' => $totalMinutes,
                            ],
                            'created_at' => $now,
                        ]
                    );
                }

                CallSession::whereIn('id', $sessionIds)->update([
                    'charged_at' => $now,
                ]);

                $chargedCount += count($sessionIds);
            });
        }

        return $chargedCount;
    }
}
