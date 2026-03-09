<?php

namespace App\Services;

class PhoneBillingService
{
    public function computeCost(int $durationSeconds): array
    {
        $freeSeconds = (int) config('voice.free_seconds_threshold', 10);
        $costPerMinute = (float) config('voice.cost_per_minute_eur', 0.05);

        if ($durationSeconds <= 0 || $durationSeconds < $freeSeconds) {
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
}
