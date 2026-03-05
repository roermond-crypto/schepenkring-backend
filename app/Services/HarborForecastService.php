<?php

namespace App\Services;

use App\Models\Yacht;

class HarborForecastService
{
    private const WEIGHTS = [
        'draft' => 0.0,
        'listed' => 0.3,
        'offer_received' => 0.5,
        'offer_accepted' => 0.7,
        'in_escrow' => 0.9,
        'delivered' => 1.0,
        'cancelled' => 0.0,
    ];

    public function buildForHarbor(int $harborId): array
    {
        $boats = Yacht::query()
            ->where('ref_harbor_id', $harborId)
            ->get([
                'id',
                'boat_name',
                'sale_price',
                'commission_percentage',
                'commission_amount',
                'sale_stage',
                'status',
            ]);

        $maxPotential = 0.0;
        $weighted = 0.0;
        $realized = 0.0;
        $breakdown = [];

        foreach ($boats as $boat) {
            $stage = $this->resolveStage($boat);
            $commission = $this->resolveCommission($boat);

            $maxPotential += $commission;
            $weighted += $commission * (self::WEIGHTS[$stage] ?? 0.0);
            if ($stage === 'delivered') {
                $realized += $commission;
            }

            $breakdown[] = [
                'boat_id' => $boat->id,
                'boat_name' => $boat->boat_name,
                'stage' => $stage,
                'commission' => $commission,
                'weight' => self::WEIGHTS[$stage] ?? 0.0,
            ];
        }

        return [
            'harbor_id' => $harborId,
            'totals' => [
                'max_potential' => round($maxPotential, 2),
                'weighted_forecast' => round($weighted, 2),
                'realized' => round($realized, 2),
            ],
            'boats' => $breakdown,
        ];
    }

    private function resolveCommission(Yacht $boat): float
    {
        if ($boat->commission_amount !== null) {
            return (float) $boat->commission_amount;
        }

        if ($boat->sale_price !== null && $boat->commission_percentage !== null) {
            return round(((float) $boat->sale_price) * ((float) $boat->commission_percentage) / 100, 2);
        }

        return 0.0;
    }

    private function resolveStage(Yacht $boat): string
    {
        if (!empty($boat->sale_stage)) {
            return (string) $boat->sale_stage;
        }

        $status = strtolower((string) $boat->status);

        return match ($status) {
            'draft' => 'draft',
            'for sale' => 'listed',
            'for bid' => 'offer_received',
            'sold' => 'delivered',
            default => 'draft',
        };
    }
}
