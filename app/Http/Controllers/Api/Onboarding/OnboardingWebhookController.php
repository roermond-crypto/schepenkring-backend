<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\BuyerVerificationSignhostTransaction;
use App\Models\SellerOnboardingPayment;
use App\Models\SellerOnboardingSignhostTransaction;
use App\Models\WebhookEvent;
use App\Services\BuyerVerificationOrchestrator;
use App\Services\MollieService;
use App\Services\SellerOnboardingOrchestrator;
use App\Services\SignhostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class OnboardingWebhookController extends Controller
{
    public function mollie(Request $request, MollieService $mollie, SellerOnboardingOrchestrator $orchestrator): JsonResponse
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'Missing id'], 200);
        }

        $payment = SellerOnboardingPayment::where('mollie_payment_id', $id)->first();
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 200);
        }

        $remote = $mollie->getPayment($id);
        $status = $this->normalizeMollieStatus($remote['status'] ?? 'open');

        $eventKey = 'mollie:' . $id . ':' . $status;
        $claim = $this->claimWebhookEvent('mollie', $eventKey, $id, $remote);
        if ($claim['status'] !== 'claimed') {
            return response()->json(['message' => 'Already processed'], 200);
        }

        try {
            $orchestrator->handlePaymentStatus($payment, $status);
            $this->markWebhookProcessed($claim['event']);
        } catch (\Throwable $e) {
            Log::error('Mollie onboarding webhook failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Processing failed'], 500);
        }

        return response()->json(['message' => 'ok']);
    }

    public function signhost(Request $request, SignhostService $signhost, SellerOnboardingOrchestrator $sellerOrchestrator, BuyerVerificationOrchestrator $buyerOrchestrator): JsonResponse
    {
        $payload = $request->all();
        $transactionId = $payload['TransactionId'] ?? $payload['transactionId'] ?? null;
        $status = $payload['Status'] ?? $payload['status'] ?? null;

        if (!$transactionId || !$status) {
            return response()->json(['message' => 'Missing data'], 200);
        }

        $eventKey = 'signhost:' . $transactionId . ':' . $status;
        $claim = $this->claimWebhookEvent('signhost', $eventKey, $transactionId, $payload);
        if ($claim['status'] !== 'claimed') {
            return response()->json(['message' => 'Already processed'], 200);
        }

        $mappedStatus = $this->mapSignhostStatus($status);

        try {
            $sellerTx = SellerOnboardingSignhostTransaction::where('signhost_transaction_id', $transactionId)->first();
            if ($sellerTx) {
                $sellerOrchestrator->handleSignhostStatus($sellerTx, $mappedStatus, $payload);
                $this->markWebhookProcessed($claim['event']);
                return response()->json(['message' => 'ok']);
            }

            $buyerTx = BuyerVerificationSignhostTransaction::where('signhost_transaction_id', $transactionId)->first();
            if ($buyerTx) {
                $buyerOrchestrator->handleSignhostStatus($buyerTx, $mappedStatus, $payload);
                $this->markWebhookProcessed($claim['event']);
                return response()->json(['message' => 'ok']);
            }

            return response()->json(['message' => 'Transaction not found'], 200);
        } catch (\Throwable $e) {
            Log::error('Signhost onboarding webhook failed', ['id' => $transactionId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Processing failed'], 500);
        }
    }

    private function claimWebhookEvent(string $provider, string $eventKey, ?string $idempotencyKey, array $payload): array
    {
        return DB::transaction(function () use ($provider, $eventKey, $idempotencyKey, $payload) {
            $event = WebhookEvent::where('event_key', $eventKey)->lockForUpdate()->first();
            if ($event && $event->processed_at) {
                return ['status' => 'processed', 'event' => $event];
            }

            if (!$event) {
                $event = WebhookEvent::create([
                    'provider' => $provider,
                    'event_key' => $eventKey,
                    'idempotency_key' => $idempotencyKey,
                    'payload_json' => $payload,
                    'processing_at' => now(),
                ]);
                return ['status' => 'claimed', 'event' => $event];
            }

            $event->processing_at = now();
            $event->save();
            return ['status' => 'claimed', 'event' => $event];
        });
    }

    private function markWebhookProcessed(WebhookEvent $event): void
    {
        $event->update(['processed_at' => now(), 'processing_at' => null]);
    }

    private function normalizeMollieStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'paid',
            'canceled' => 'canceled',
            'failed' => 'failed',
            'expired' => 'expired',
            'authorized' => 'authorized',
            default => 'open',
        };
    }

    private function mapSignhostStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'DRAFT', 'REQUESTED' => 'pending',
            'SENT', 'VIEWED' => 'signing',
            'SIGNED' => 'signed',
            'DECLINED' => 'rejected',
            'EXPIRED' => 'expired',
            'FAILED' => 'cancelled',
            default => 'signing',
        };
    }
}
