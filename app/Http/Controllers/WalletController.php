<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletLedger;
use App\Models\WalletTopup;
use App\Services\MollieService;
use App\Services\SystemLogService;
use App\Services\WalletLedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function __construct(private WalletLedgerService $ledgerService)
    {
    }

    public function ledger(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $this->ledgerFor($request, $user);
    }

    public function balances(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $currency = $request->query('currency');

        return response()->json($this->ledgerService->computeBalances($user->id, $currency));
    }

    public function createTopup(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'redirect_url' => 'required|url',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $currency = strtoupper($validated['currency'] ?? config('wallet.default_currency', 'EUR'));
        $amountValue = number_format((float) $validated['amount'], 2, '.', '');

        $idempotencyKey = $request->header('Idempotency-Key') ?? $validated['idempotency_key'];
        if (!$idempotencyKey) {
            $idempotencyKey = hash('sha256', implode('|', [
                'wallet',
                $user->id,
                $currency,
                $amountValue,
            ]));
        }

        $existing = WalletTopup::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return response()->json([
                'topup' => $existing,
                'checkout_url' => $existing->checkout_url,
            ]);
        }

        $payload = [
            'amount' => [
                'currency' => $currency,
                'value' => $amountValue,
            ],
            'description' => "Wallet top-up for user {$user->id}",
            'redirectUrl' => $validated['redirect_url'],
            'webhookUrl' => url('/api/webhooks/mollie'),
            'metadata' => [
                'payment_type' => 'wallet_topup',
                'user_id' => $user->id,
            ],
        ];

        $mollie = new MollieService();
        $response = $mollie->createPayment($payload, $idempotencyKey);

        $topup = WalletTopup::create([
            'user_id' => $user->id,
            'mollie_payment_id' => $response['id'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'amount_currency' => $currency,
            'amount_value' => $amountValue,
            'status' => $response['status'] ?? 'open',
            'checkout_url' => $response['_links']['checkout']['href'] ?? null,
            'webhook_events_count' => 0,
        ]);

        SystemLogService::log(
            'wallet_topup_created',
            'WalletTopup',
            $topup->id,
            null,
            [
                'user_id' => $user->id,
                'amount' => $amountValue,
                'currency' => $currency,
                'status' => $topup->status,
            ],
            "Wallet top-up created for user {$user->id}",
            $request
        );

        return response()->json([
            'topup' => $topup,
            'checkout_url' => $topup->checkout_url,
        ], 201);
    }

    public function ledgerForUser(User $user, Request $request)
    {
        $actor = $request->user();
        if (!$actor || strtolower((string) $actor->role) !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $this->ledgerFor($request, $user);
    }

    private function ledgerFor(Request $request, User $user)
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in([
                WalletLedger::TYPE_COMMISSION_PENDING,
                WalletLedger::TYPE_COMMISSION_REALIZED,
                WalletLedger::TYPE_HARBOR_SPLIT,
                WalletLedger::TYPE_LISTING_FEE,
                WalletLedger::TYPE_REFUND,
                WalletLedger::TYPE_PAYOUT,
                WalletLedger::TYPE_CORRECTION,
                WalletLedger::TYPE_LOCKED,
                WalletLedger::TYPE_VOICE_USAGE,
                WalletLedger::TYPE_TOPUP,
            ])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = WalletLedger::query()->where('user_id', $user->id);

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $perPage = $validated['per_page'] ?? 25;

        return response()->json($query->orderByDesc('id')->paginate($perPage));
    }
}
