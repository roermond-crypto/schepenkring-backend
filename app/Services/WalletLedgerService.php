<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Payment;
use App\Models\Yacht;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\Log;

class WalletLedgerService
{
    private const PENDING_TYPES = [
        WalletLedger::TYPE_COMMISSION_PENDING,
    ];

    private const LOCKED_TYPES = [
        WalletLedger::TYPE_LOCKED,
    ];

    private const AVAILABLE_TYPES = [
        WalletLedger::TYPE_COMMISSION_REALIZED,
        WalletLedger::TYPE_HARBOR_SPLIT,
        WalletLedger::TYPE_LISTING_FEE,
        WalletLedger::TYPE_REFUND,
        WalletLedger::TYPE_PAYOUT,
        WalletLedger::TYPE_CORRECTION,
        WalletLedger::TYPE_VOICE_USAGE,
        WalletLedger::TYPE_TOPUP,
    ];

    public function recordMollieStatus(Payment $payment, string $status): void
    {
        $normalized = $this->normalizeStatus($status);
        $eventKey = 'mollie:' . $payment->mollie_payment_id . ':' . $normalized;

        if (!in_array($normalized, ['paid', 'refunded', 'chargeback'], true)) {
            return;
        }

        if ($payment->type !== 'platform_fee') {
            return;
        }

        $deal = Deal::with('boat')->find($payment->deal_id);
        if (!$deal || !$deal->boat) {
            Log::warning('Wallet ledger skipped: missing deal/boat', ['payment_id' => $payment->id]);
            return;
        }

        $boat = $deal->boat;
        $commissionTotal = $this->normalizeAmount(
            $boat->commission_amount ?? $payment->amount_value
        );
        if ($commissionTotal === null) {
            return;
        }

        $harborSplitPct = (float) ($boat->harbor_split_percentage ?? 0);
        $harborAmount = round($commissionTotal * $harborSplitPct / 100, 2);
        $platformAmount = round($commissionTotal - $harborAmount, 2);

        $currency = strtoupper((string) $payment->amount_currency);
        $platformUserId = config('wallet.platform_user_id');

        if ($normalized === 'paid') {
            if ($platformUserId) {
                $this->createLedgerEntry(
                    (int) $platformUserId,
                    WalletLedger::TYPE_COMMISSION_PENDING,
                    $platformAmount,
                    $currency,
                    $payment,
                    $eventKey,
                    ['deal_id' => $deal->id, 'boat_id' => $boat->id]
                );
            }

            if ($boat->ref_harbor_id) {
                $this->createLedgerEntry(
                    (int) $boat->ref_harbor_id,
                    WalletLedger::TYPE_COMMISSION_PENDING,
                    $harborAmount,
                    $currency,
                    $payment,
                    $eventKey,
                    ['deal_id' => $deal->id, 'boat_id' => $boat->id, 'harbor_split_pct' => $harborSplitPct]
                );
            }

            return;
        }

        if ($normalized === 'refunded') {
            $this->createRefundEntries($payment, $eventKey, $platformUserId, $boat->ref_harbor_id, $platformAmount, $harborAmount, $currency);
            return;
        }

        if ($normalized === 'chargeback') {
            $this->createLockedEntries($payment, $eventKey, $platformUserId, $boat->ref_harbor_id, $platformAmount, $harborAmount, $currency);
        }
    }

    public function recordWalletTopupStatus(\App\Models\WalletTopup $topup, string $status): void
    {
        $normalized = $this->normalizeStatus($status);
        $eventKey = 'mollie:' . $topup->mollie_payment_id . ':' . $normalized;

        if (!in_array($normalized, ['paid', 'refunded', 'chargeback'], true)) {
            return;
        }

        $amount = $this->normalizeAmount($topup->amount_value);
        if ($amount === null) {
            return;
        }

        $currency = strtoupper((string) $topup->amount_currency);
        $userId = (int) $topup->user_id;

        if ($normalized === 'paid') {
            $this->createTopupLedgerEntry(
                $userId,
                WalletLedger::TYPE_TOPUP,
                $amount,
                $currency,
                $topup,
                $eventKey,
                ['reason' => 'wallet_topup']
            );
            return;
        }

        if ($normalized === 'refunded') {
            $this->createTopupLedgerEntry(
                $userId,
                WalletLedger::TYPE_REFUND,
                -1 * abs($amount),
                $currency,
                $topup,
                $eventKey,
                ['reason' => 'wallet_topup_refund']
            );
            return;
        }

        if ($normalized === 'chargeback') {
            $this->createTopupLedgerEntry(
                $userId,
                WalletLedger::TYPE_LOCKED,
                -1 * abs($amount),
                $currency,
                $topup,
                $eventKey,
                ['reason' => 'wallet_topup_chargeback']
            );
        }
    }

    public function computeBalances(int $userId, ?string $currency = null): array
    {
        $baseQuery = WalletLedger::query()->where('user_id', $userId);
        if ($currency) {
            $baseQuery->where('currency', strtoupper($currency));
        }

        $pending = (clone $baseQuery)->whereIn('type', self::PENDING_TYPES)->sum('amount');
        $locked = (clone $baseQuery)->whereIn('type', self::LOCKED_TYPES)->sum('amount');
        $available = (clone $baseQuery)->whereIn('type', self::AVAILABLE_TYPES)->sum('amount');

        return [
            'available' => (float) $available,
            'pending' => (float) $pending,
            'locked' => (float) $locked,
            'currency' => $currency ? strtoupper($currency) : null,
        ];
    }

    public function recordCommissionRealizedForBoat(Yacht $boat): void
    {
        $commissionTotal = $this->normalizeAmount(
            $boat->commission_amount ?? ($boat->sale_price && $boat->commission_percentage
                ? ((float) $boat->sale_price) * ((float) $boat->commission_percentage) / 100
                : null)
        );

        if ($commissionTotal === null) {
            return;
        }

        $harborSplitPct = (float) ($boat->harbor_split_percentage ?? 0);
        $harborAmount = round($commissionTotal * $harborSplitPct / 100, 2);
        $platformAmount = round($commissionTotal - $harborAmount, 2);
        $currency = config('wallet.default_currency', 'EUR');
        $eventKey = 'boat:' . $boat->id . ':delivered';

        $platformUserId = config('wallet.platform_user_id');
        if ($platformUserId) {
            $this->createBoatLedgerEntry((int) $platformUserId, WalletLedger::TYPE_COMMISSION_PENDING, -1 * abs($platformAmount), $currency, $boat, $eventKey, [
                'reason' => 'commission_realized',
            ]);
            $this->createBoatLedgerEntry((int) $platformUserId, WalletLedger::TYPE_COMMISSION_REALIZED, $platformAmount, $currency, $boat, $eventKey, [
                'reason' => 'commission_realized',
            ]);
        }

        if ($boat->ref_harbor_id) {
            $this->createBoatLedgerEntry((int) $boat->ref_harbor_id, WalletLedger::TYPE_COMMISSION_PENDING, -1 * abs($harborAmount), $currency, $boat, $eventKey, [
                'reason' => 'commission_realized',
            ]);
            $this->createBoatLedgerEntry((int) $boat->ref_harbor_id, WalletLedger::TYPE_COMMISSION_REALIZED, $harborAmount, $currency, $boat, $eventKey, [
                'reason' => 'commission_realized',
            ]);
        }
    }

    private function createRefundEntries(
        Payment $payment,
        string $eventKey,
        $platformUserId,
        $harborUserId,
        float $platformAmount,
        float $harborAmount,
        string $currency
    ): void {
        if ($platformUserId) {
            $this->createLedgerEntry(
                (int) $platformUserId,
                WalletLedger::TYPE_REFUND,
                -1 * abs($platformAmount),
                $currency,
                $payment,
                $eventKey,
                ['reason' => 'mollie_refund']
            );
        }

        if ($harborUserId) {
            $this->createLedgerEntry(
                (int) $harborUserId,
                WalletLedger::TYPE_REFUND,
                -1 * abs($harborAmount),
                $currency,
                $payment,
                $eventKey,
                ['reason' => 'mollie_refund']
            );
        }
    }

    private function createLockedEntries(
        Payment $payment,
        string $eventKey,
        $platformUserId,
        $harborUserId,
        float $platformAmount,
        float $harborAmount,
        string $currency
    ): void {
        if ($platformUserId) {
            $this->createLedgerEntry(
                (int) $platformUserId,
                WalletLedger::TYPE_LOCKED,
                -1 * abs($platformAmount),
                $currency,
                $payment,
                $eventKey,
                ['reason' => 'mollie_chargeback']
            );
        }

        if ($harborUserId) {
            $this->createLedgerEntry(
                (int) $harborUserId,
                WalletLedger::TYPE_LOCKED,
                -1 * abs($harborAmount),
                $currency,
                $payment,
                $eventKey,
                ['reason' => 'mollie_chargeback']
            );
        }
    }

    private function createLedgerEntry(
        int $userId,
        string $type,
        float $amount,
        string $currency,
        Payment $payment,
        string $eventKey,
        array $metadata = []
    ): void {
        if ($amount === 0.0) {
            return;
        }

        WalletLedger::firstOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
                'reference_type' => 'mollie_payment',
                'reference_id' => $payment->id,
                'reference_key' => $eventKey,
            ],
            [
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => array_merge([
                    'mollie_payment_id' => $payment->mollie_payment_id,
                    'payment_type' => $payment->type,
                ], $metadata),
            ]
        );
    }

    private function createTopupLedgerEntry(
        int $userId,
        string $type,
        float $amount,
        string $currency,
        \App\Models\WalletTopup $topup,
        string $eventKey,
        array $metadata = []
    ): void {
        if ($amount === 0.0) {
            return;
        }

        WalletLedger::firstOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
                'reference_type' => 'wallet_topup',
                'reference_id' => $topup->id,
                'reference_key' => $eventKey,
            ],
            [
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => array_merge([
                    'mollie_payment_id' => $topup->mollie_payment_id,
                    'payment_type' => 'wallet_topup',
                ], $metadata),
            ]
        );
    }

    private function createBoatLedgerEntry(
        int $userId,
        string $type,
        float $amount,
        string $currency,
        Yacht $boat,
        string $eventKey,
        array $metadata = []
    ): void {
        if ($amount === 0.0) {
            return;
        }

        WalletLedger::firstOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
                'reference_type' => 'yacht',
                'reference_id' => $boat->id,
                'reference_key' => $eventKey,
            ],
            [
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => array_merge([
                    'boat_id' => $boat->id,
                ], $metadata),
            ]
        );
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);
        if ($status === 'charged_back') {
            return 'chargeback';
        }

        return $status;
    }

    private function normalizeAmount($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) number_format((float) $value, 2, '.', '');
    }
}
