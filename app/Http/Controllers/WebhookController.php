<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Payment;
use App\Models\SignhostTransaction;
use App\Models\WalletTopup;
use App\Models\PartnerContract;
use App\Models\PartnerProfile;
use App\Models\WebhookEvent;
use App\Services\DealStateMachine;
use App\Services\MollieService;
use App\Services\WalletLedgerService;
use App\Services\SignhostService;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class WebhookController extends Controller
{
    public function mollie(Request $request)
    {
        if ($this->isRateLimited($request, 'mollie')) {
            return response()->json(['message' => 'Rate limit exceeded'], 429);
        }

        if (!$this->verifyWebhookTimestamp($request, 'mollie')) {
            Log::warning('Mollie webhook timestamp too old');
            return response()->json(['message' => 'Stale webhook'], 400);
        }

        if (!$this->verifyMollieSignature($request)) {
            Log::warning('Mollie webhook invalid signature');
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Mollie webhook invalid signature', \Sentry\Severity::warning());
            }
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $paymentId = $request->input('id') ?? $request->input('payment_id');
        if (!$paymentId) {
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Mollie webhook missing payment id', \Sentry\Severity::warning());
            }
            return response()->json(['message' => 'Missing payment id'], 200);
        }

        $mollie = new MollieService();
        $remote = $mollie->getPayment($paymentId);
        $status = $this->normalizeMollieStatus($remote['status'] ?? 'open');
        $eventKey = 'mollie:' . $paymentId . ':' . $status;
        $idempotencyKey = $request->header('X-Webhook-Id')
            ?? $request->header('X-Event-Id')
            ?? $request->header('Idempotency-Key')
            ?? $eventKey;

        $existing = WebhookEvent::where('idempotency_key', $idempotencyKey)->first()
            ?? WebhookEvent::where('event_key', $eventKey)->first();
        if ($existing && $existing->processed_at) {
            return response()->json(['message' => 'Already processed'], 200);
        }

        $event = $existing ?: WebhookEvent::create([
            'provider' => 'mollie',
            'event_key' => $eventKey,
            'idempotency_key' => $idempotencyKey,
            'payload_json' => [
                'webhook' => $request->all(),
                'remote' => $remote,
            ],
            'processed_at' => null,
        ]);

        $payment = Payment::where('mollie_payment_id', $paymentId)->first();
        $topup = null;
        if (!$payment) {
            $topup = WalletTopup::where('mollie_payment_id', $paymentId)->first();
        }

        if (!$payment && !$topup) {
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Mollie webhook payment not found', \Sentry\Severity::warning());
            }
            $event->update(['processed_at' => now()]);
            return response()->json(['message' => 'Payment not found'], 200);
        }

        $metadata = $remote['metadata'] ?? [];
        if ($payment) {
            if ((int) ($metadata['deal_id'] ?? 0) !== $payment->deal_id || ($metadata['payment_type'] ?? '') !== $payment->type) {
                Log::warning('Mollie metadata mismatch', ['payment_id' => $paymentId]);
                if (function_exists('\\Sentry\\captureMessage')) {
                    \Sentry\captureMessage('Mollie webhook metadata mismatch', \Sentry\Severity::warning());
                }
                $event->update(['processed_at' => now()]);
                return response()->json(['message' => 'Metadata mismatch'], 200);
            }
        } else {
            if ((int) ($metadata['user_id'] ?? 0) !== (int) $topup->user_id || ($metadata['payment_type'] ?? '') !== 'wallet_topup') {
                Log::warning('Mollie metadata mismatch (wallet topup)', ['payment_id' => $paymentId]);
                if (function_exists('\\Sentry\\captureMessage')) {
                    \Sentry\captureMessage('Mollie webhook metadata mismatch (wallet topup)', \Sentry\Severity::warning());
                }
                $event->update(['processed_at' => now()]);
                return response()->json(['message' => 'Metadata mismatch'], 200);
            }
        }

        $amount = $remote['amount']['value'] ?? null;
        $currency = $remote['amount']['currency'] ?? null;
        if ($amount === null || $currency === null) {
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Mollie webhook amount missing', \Sentry\Severity::warning());
            }
            $event->update(['processed_at' => now()]);
            return response()->json(['message' => 'Amount missing'], 200);
        }

        $localAmount = $payment ? $payment->amount_value : $topup->amount_value;
        $localCurrency = $payment ? $payment->amount_currency : $topup->amount_currency;
        if (number_format((float) $amount, 2, '.', '') !== number_format((float) $localAmount, 2, '.', '') ||
            strtoupper($currency) !== strtoupper($localCurrency)) {
            Log::warning('Mollie amount mismatch', ['payment_id' => $paymentId]);
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Mollie webhook amount mismatch', \Sentry\Severity::warning());
            }
            $event->update(['processed_at' => now()]);
            return response()->json(['message' => 'Amount mismatch'], 200);
        }

        try {
            $previousStatus = $payment ? $payment->status : $topup->status;
            DB::transaction(function () use ($payment, $topup, $status) {
                if ($payment) {
                    $payment->status = $status;
                    $payment->webhook_events_count = $payment->webhook_events_count + 1;
                    $payment->save();

                    app(WalletLedgerService::class)->recordMollieStatus($payment, $status);
                    $this->advanceDealFromPayment($payment);
                    return;
                }

                $topup->status = $status;
                $topup->webhook_events_count = $topup->webhook_events_count + 1;
                $topup->save();

                app(WalletLedgerService::class)->recordWalletTopupStatus($topup, $status);
            });
        } catch (\Throwable $e) {
            $entityId = $payment?->id ?? $topup?->id;
            Log::error('Mollie webhook processing failed', ['payment_id' => $entityId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Processing failed'], 500);
        }

        if ($payment) {
            SystemLogService::log(
                'payment_status_updated',
                'Payment',
                $payment->id,
                ['status' => $previousStatus],
                ['status' => $status],
                "Payment {$payment->id} status updated",
                $request
            );
        } else {
            SystemLogService::log(
                'wallet_topup_status_updated',
                'WalletTopup',
                $topup->id,
                ['status' => $previousStatus],
                ['status' => $status],
                "Wallet topup {$topup->id} status updated",
                $request
            );
        }

        $event->update(['processed_at' => now()]);

        return response()->json(['message' => 'ok'], 200);
    }

    private function verifyMollieSignature(Request $request): bool
    {
        $secret = (string) config('services.mollie.webhook_secret');
        if ($secret === '') {
            return true;
        }

        $signature = $request->header('X-Mollie-Signature') ?? $request->header('X-Webhook-Signature');
        if (!$signature) {
            return false;
        }

        $computed = hash_hmac('sha256', (string) $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }

    private function verifyWebhookTimestamp(Request $request, string $provider): bool
    {
        $maxAge = (int) config('security.webhooks.max_age_seconds', 300);
        if ($maxAge <= 0) {
            return true;
        }

        $timestamp = $request->header('X-Webhook-Timestamp')
            ?? $request->header('X-Signature-Timestamp')
            ?? $request->header('X-Mollie-Timestamp')
            ?? $request->header('X-Signhost-Timestamp');

        if (!$timestamp) {
            return true;
        }

        $ts = is_numeric($timestamp) ? (int) $timestamp : strtotime((string) $timestamp);
        if (!$ts) {
            Log::warning('Webhook timestamp parse failed', ['provider' => $provider]);
            return false;
        }

        $age = abs(now()->getTimestamp() - $ts);
        if ($age > $maxAge) {
            Log::warning('Webhook timestamp outside allowed window', ['provider' => $provider, 'age' => $age]);
            return false;
        }

        return true;
    }

    public function signhost(Request $request)
    {
        if ($this->isRateLimited($request, 'signhost')) {
            return response()->json(['message' => 'Rate limit exceeded'], 429);
        }

        if (!$this->verifyWebhookTimestamp($request, 'signhost')) {
            Log::warning('Signhost webhook timestamp too old');
            return response()->json(['message' => 'Stale webhook'], 400);
        }

        $payload = $request->all();
        $checksum = $request->header('X-Signhost-Checksum') ?? $request->input('checksum');
        $authHeader = config('services.signhost.webhook_auth');
        if ($authHeader) {
            $incoming = $request->header('Authorization');
            if ($incoming !== $authHeader) {
                Log::warning('Signhost webhook invalid auth header');
                if (function_exists('\\Sentry\\captureMessage')) {
                    \Sentry\captureMessage('Signhost webhook invalid auth header', \Sentry\Severity::warning());
                }
                return response()->json(['message' => 'Invalid auth'], 200);
            }
        }

        $signhost = new SignhostService();
        if (!$checksum || !$signhost->verifyWebhook($payload, $checksum)) {
            Log::warning('Signhost webhook invalid checksum');
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Signhost webhook invalid checksum', \Sentry\Severity::warning());
            }
            return response()->json(['message' => 'Invalid checksum'], 200);
        }

        $transactionId = $payload['TransactionId'] ?? $payload['transactionId'] ?? null;
        $status = $payload['Status'] ?? $payload['status'] ?? null;
        if (!$transactionId || !$status) {
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Signhost webhook missing data', \Sentry\Severity::warning());
            }
            return response()->json(['message' => 'Missing data'], 200);
        }

        $eventKey = 'signhost:' . $transactionId . ':' . $status;
        $idempotencyKey = $request->header('X-Webhook-Id')
            ?? $request->header('X-Event-Id')
            ?? $request->header('Idempotency-Key')
            ?? $eventKey;
        $existing = WebhookEvent::where('idempotency_key', $idempotencyKey)->first()
            ?? WebhookEvent::where('event_key', $eventKey)->first();
        if ($existing && $existing->processed_at) {
            return response()->json(['message' => 'Already processed'], 200);
        }

        $event = $existing ?: WebhookEvent::create([
            'provider' => 'signhost',
            'event_key' => $eventKey,
            'idempotency_key' => $idempotencyKey,
            'payload_json' => $payload,
            'processed_at' => null,
        ]);

        $transaction = SignhostTransaction::where('signhost_transaction_id', $transactionId)->first();
        if ($transaction) {
            $previousStatus = $transaction->status;
            $transaction->status = $this->mapSignhostStatus($status);
            $transaction->webhook_last_payload = $payload;
            $transaction->save();

            if ($transaction->status === 'signed') {
                $deal = Deal::find($transaction->deal_id);
                if ($deal) {
                    $state = new DealStateMachine();
                    $state->transition($deal, 'contract_signed');
                    $this->storeSignedContract($signhost, $transaction, $deal);
                    
                    // Dispatch AI compliance job when boat contract is signed
                    if ($deal->boat_id) {
                        \App\Jobs\ProcessBoatComplianceAi::dispatch($deal->boat_id);
                    }
                }
            }

            $event->update(['processed_at' => now()]);

            SystemLogService::log(
                'signhost_status_updated',
                'SignhostTransaction',
                $transaction->id,
                ['status' => $previousStatus],
                ['status' => $transaction->status],
                "Signhost status updated for transaction {$transaction->signhost_transaction_id}",
                $request
            );

            return response()->json(['message' => 'ok'], 200);
        }

        $partnerContract = PartnerContract::where('signhost_transaction_id', $transactionId)->first();
        if (!$partnerContract) {
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Signhost webhook transaction not found', \Sentry\Severity::warning());
            }
            $event->update(['processed_at' => now()]);
            return response()->json(['message' => 'Transaction not found'], 200);
        }

        $previousStatus = $partnerContract->status;
        $partnerContract->status = $this->mapSignhostStatus($status);
        $partnerContract->save();

        if ($partnerContract->status === 'signed') {
            $this->storeSignedPartnerContract($signhost, $partnerContract);
        }

        $event->update(['processed_at' => now()]);

        SystemLogService::log(
            'signhost_status_updated',
            'PartnerContract',
            $partnerContract->id,
            ['status' => $previousStatus],
            ['status' => $partnerContract->status],
            "Signhost status updated for partner contract {$partnerContract->signhost_transaction_id}",
            $request
        );

        return response()->json(['message' => 'ok'], 200);
    }

    private function isRateLimited(Request $request, string $provider): bool
    {
        $limit = (int) config('security.webhooks.rate_limit_per_minute', 120);
        $key = 'webhook:' . $provider . ':' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('Webhook rate limit exceeded', ['provider' => $provider, 'ip' => $request->ip()]);
            return true;
        }

        RateLimiter::hit($key, 60);
        return false;
    }

    private function advanceDealFromPayment(Payment $payment): void
    {
        if ($payment->status !== 'paid') {
            return;
        }

        $deal = Deal::find($payment->deal_id);
        if (!$deal) {
            return;
        }

        $state = new DealStateMachine();

        if ($payment->type === 'deposit') {
            try {
                $state->transition($deal, 'deposit_paid');
            } catch (\Throwable $e) {
            }
        }

        if ($payment->type === 'platform_fee') {
            try {
                $state->transition($deal, 'platform_fee_paid');
            } catch (\Throwable $e) {
            }
        }

        $hasDeposit = Payment::where('deal_id', $deal->id)
            ->where('type', 'deposit')
            ->where('status', 'paid')
            ->exists();
        $hasFee = Payment::where('deal_id', $deal->id)
            ->where('type', 'platform_fee')
            ->where('status', 'paid')
            ->exists();

        if ($hasDeposit && $hasFee) {
            try {
                $state->transition($deal, 'completed');
            } catch (\Throwable $e) {
            }
        }
    }

    private function storeSignedContract(SignhostService $signhost, SignhostTransaction $transaction, Deal $deal): void
    {
        $signed = $signhost->downloadSignedFile($transaction->signhost_transaction_id);
        if (!$signed) {
            return;
        }

        $path = "contracts/deal_{$deal->id}_signed.pdf";
        Storage::put($path, $signed);
        $transaction->signed_pdf_path = $path;
        $transaction->save();
    }

    private function storeSignedPartnerContract(SignhostService $signhost, PartnerContract $contract): void
    {
        $signed = $signhost->downloadSignedFile($contract->signhost_transaction_id);
        if (!$signed) {
            return;
        }

        $path = "contracts/partner_{$contract->user_id}_signed.pdf";
        Storage::put($path, $signed);
        $contract->signed_document_url = $path;
        $contract->signed_at = now();
        $contract->save();

        $profile = PartnerProfile::where('user_id', $contract->user_id)->first();
        if ($profile) {
            $profile->contract_signed_at = now();
            $profile->save();
        }

        $user = $contract->user;
        if ($user) {
            $user->status = 'active';
            $user->save();
        }
    }

    private function mapSignhostStatus(string $status): string
    {
        $status = strtolower($status);
        return match ($status) {
            'signed' => 'signed',
            'rejected' => 'rejected',
            'expired' => 'expired',
            'cancelled' => 'cancelled',
            default => 'signing',
        };
    }

    private function normalizeMollieStatus(string $status): string
    {
        $status = strtolower($status);
        if ($status === 'charged_back') {
            return 'chargeback';
        }

        return $status;
    }
}
