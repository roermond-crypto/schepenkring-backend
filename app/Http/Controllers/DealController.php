<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Payment;
use App\Models\SignhostTransaction;
use App\Services\DealStateMachine;
use App\Services\MollieService;
use App\Services\SignhostService;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DealController extends Controller
{
    public function status($dealId)
    {
        $deal = Deal::with(['signhostTransactions', 'payments'])->findOrFail($dealId);
        $this->authorizeDeal($deal);

        return response()->json($deal);
    }

    public function generateContract(Request $request, $dealId)
    {
        $deal = Deal::with(['buyer', 'seller'])->findOrFail($dealId);
        $this->authorizeDeal($deal);

        $state = new DealStateMachine();
        $state->transition($deal, 'contract_prepared');

        $path = $this->generateSimplePdf($deal);
        $deal->contract_pdf_path = $path;
        $deal->contract_sha256 = hash_file('sha256', Storage::path($path));
        $deal->save();

        SystemLogService::log(
            'contract_generated',
            'Deal',
            $deal->id,
            null,
            [
                'contract_pdf_path' => $deal->contract_pdf_path,
                'contract_sha256' => $deal->contract_sha256,
            ],
            "Contract generated for deal {$deal->id}",
            $request
        );

        return response()->json([
            'message' => 'Contract generated',
            'contract_pdf_path' => $deal->contract_pdf_path,
            'contract_sha256' => $deal->contract_sha256,
        ]);
    }

    public function createSignhost(Request $request, $dealId)
    {
        $deal = Deal::with(['buyer', 'seller'])->findOrFail($dealId);
        $this->authorizeDeal($deal);

        if (!$deal->contract_pdf_path) {
            return response()->json(['message' => 'Contract not prepared'], 422);
        }

        $state = new DealStateMachine();
        $state->transition($deal, 'signhost_transaction_created');

        $service = new SignhostService();
        $result = $service->createTransaction(
            $deal,
            Storage::path($deal->contract_pdf_path),
            $deal->buyer,
            $deal->seller
        );

        $transaction = $result['transaction'] ?? [];
        $signingUrls = $this->extractSigningUrls($transaction);

        $record = SignhostTransaction::create([
            'deal_id' => $deal->id,
            'signhost_transaction_id' => $result['transaction_id'],
            'status' => 'pending',
            'signing_url_buyer' => $signingUrls['buyer'] ?? null,
            'signing_url_seller' => $signingUrls['seller'] ?? null,
        ]);

        $state->transition($deal, 'signing_in_progress');

        SystemLogService::log(
            'contract_signing_started',
            'Deal',
            $deal->id,
            null,
            [
                'signhost_transaction_id' => $record->signhost_transaction_id,
            ],
            "Signing started for deal {$deal->id}",
            $request
        );

        return response()->json([
            'message' => 'Signhost transaction created',
            'transaction' => $record,
        ]);
    }

    public function getSignhostUrl(Request $request, $dealId)
    {
        $deal = Deal::with(['buyer', 'seller', 'signhostTransactions'])->findOrFail($dealId);
        $this->authorizeDeal($deal);

        $role = $request->query('role');
        $transaction = $deal->signhostTransactions()->latest()->first();
        if (!$transaction) {
            return response()->json(['message' => 'No signhost transaction'], 404);
        }

        if ($role === 'buyer') {
            return response()->json(['url' => $transaction->signing_url_buyer]);
        }
        if ($role === 'seller') {
            return response()->json(['url' => $transaction->signing_url_seller]);
        }

        return response()->json(['message' => 'Invalid role'], 422);
    }

    public function createDepositPayment(Request $request, $dealId)
    {
        return $this->createPayment($request, $dealId, 'deposit');
    }

    public function createPlatformFeePayment(Request $request, $dealId)
    {
        return $this->createPayment($request, $dealId, 'platform_fee');
    }

    public function getCheckoutUrl(Request $request, $dealId, $type)
    {
        $deal = Deal::with('boat')->findOrFail($dealId);
        $this->authorizeDeal($deal);

        $payment = Payment::where('deal_id', $deal->id)
            ->where('type', $type)
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json(['checkout_url' => $payment->checkout_url]);
    }

    private function createPayment(Request $request, $dealId, string $type)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'redirect_url' => 'required|url',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $deal = Deal::findOrFail($dealId);
        $this->authorizeDeal($deal);

        $state = new DealStateMachine();
        if ($type === 'deposit') {
            $state->transition($deal, 'payment_deposit_created');
        }

        $currency = strtoupper($validated['currency'] ?? 'EUR');
        $amountValue = number_format((float) $validated['amount'], 2, '.', '');

        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');
        if (!$idempotencyKey) {
            $idempotencyKey = hash('sha256', implode('|', [
                'deal',
                $deal->id,
                $type,
                $currency,
                $amountValue,
            ]));
        }

        $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return response()->json([
                'payment' => $existing,
                'checkout_url' => $existing->checkout_url,
            ]);
        }

        $payload = [
            'amount' => [
                'currency' => $currency,
                'value' => $amountValue,
            ],
            'description' => "Deal {$deal->id} {$type}",
            'redirectUrl' => $validated['redirect_url'],
            'webhookUrl' => url('/api/webhooks/mollie'),
            'metadata' => [
                'deal_id' => $deal->id,
                'payment_type' => $type,
                'boat_id' => $deal->boat_id,
                'harbor_id' => $deal->boat?->ref_harbor_id,
            ],
        ];

        $mollie = new MollieService();
        $response = $mollie->createPayment($payload, $idempotencyKey);

        $payment = Payment::create([
            'deal_id' => $deal->id,
            'type' => $type,
            'mollie_payment_id' => $response['id'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'amount_currency' => $currency,
            'amount_value' => $amountValue,
            'status' => $response['status'] ?? 'open',
            'checkout_url' => $response['_links']['checkout']['href'] ?? null,
            'webhook_events_count' => 0,
        ]);

        SystemLogService::log(
            'payment_created',
            'Payment',
            $payment->id,
            null,
            [
                'deal_id' => $deal->id,
                'type' => $type,
                'amount' => $amountValue,
                'currency' => $currency,
                'status' => $payment->status,
            ],
            "Payment created for deal {$deal->id}",
            $request
        );

        return response()->json([
            'payment' => $payment,
            'checkout_url' => $payment->checkout_url,
        ]);
    }

    private function authorizeDeal(Deal $deal): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthorized');
        }
        if (strtolower($user->role) === 'admin') {
            return;
        }
        if ($deal->buyer_user_id === $user->id || $deal->seller_user_id === $user->id) {
            return;
        }
        abort(403, 'Forbidden');
    }

    private function generateSimplePdf(Deal $deal): string
    {
        $content = "%PDF-1.4\n1 0 obj<<>>endobj\n2 0 obj<< /Length 64>>stream\nDeal {$deal->id} contract\nendstream\nendobj\n3 0 obj<< /Type /Page /Parent 4 0 R /Contents 2 0 R>>endobj\n4 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1>>endobj\n5 0 obj<< /Type /Catalog /Pages 4 0 R>>endobj\nxref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000050 00000 n \n0000000120 00000 n \n0000000180 00000 n \n0000000240 00000 n \ntrailer<< /Size 6 /Root 5 0 R>>\nstartxref\n300\n%%EOF";

        $path = "contracts/deal_{$deal->id}.pdf";
        Storage::put($path, $content);
        return $path;
    }

    private function extractSigningUrls(array $transaction): array
    {
        $urls = [
            'buyer' => null,
            'seller' => null,
        ];

        $signers = $transaction['Signers'] ?? $transaction['signers'] ?? [];
        if (is_array($signers) && count($signers) > 0) {
            $urls['buyer'] = $signers[0]['SignUrl'] ?? $signers[0]['signUrl'] ?? null;
            $urls['seller'] = $signers[1]['SignUrl'] ?? $signers[1]['signUrl'] ?? null;
        }

        return $urls;
    }
}
