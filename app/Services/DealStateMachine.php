<?php

namespace App\Services;

use App\Models\Deal;
use Illuminate\Validation\ValidationException;
use App\Services\Ga4MeasurementService;

class DealStateMachine
{
    public const TRANSITIONS = [
        'draft' => ['offer_made', 'cancelled'],
        'offer_made' => ['contract_prepared', 'cancelled', 'expired'],
        'contract_prepared' => ['signhost_transaction_created', 'cancelled', 'expired'],
        'signhost_transaction_created' => ['signing_in_progress', 'cancelled', 'expired'],
        'signing_in_progress' => ['contract_signed', 'cancelled', 'expired'],
        'contract_signed' => ['payment_deposit_created', 'platform_fee_paid', 'cancelled', 'expired'],
        'payment_deposit_created' => ['deposit_paid', 'cancelled', 'expired'],
        'deposit_paid' => ['platform_fee_paid', 'completed', 'cancelled'],
        'platform_fee_paid' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'expired' => [],
    ];

    public function transition(Deal $deal, string $nextStatus): Deal
    {
        $current = $deal->status;
        $allowed = self::TRANSITIONS[$current] ?? [];

        if (!in_array($nextStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid state transition from {$current} to {$nextStatus}",
            ]);
        }

        $deal->status = $nextStatus;
        $deal->save();

        if ($nextStatus === 'completed') {
            $deal->loadMissing('boat');
            $boat = $deal->boat;
            app(Ga4MeasurementService::class)->sendEvent('deal_completed', [
                'harbor_id' => $boat?->ref_harbor_id,
                'ref' => $boat?->ref_code,
                'boat_id' => $boat?->id,
                'deal_id' => $deal->id,
            ]);
        }

        return $deal;
    }
}
