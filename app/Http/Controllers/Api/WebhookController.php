<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSignhostWebhookJob;
use App\Models\BoatIntakePayment;
use App\Models\WebhookEvent;
use App\Services\MollieService;
use App\Services\SellerIntakeWorkflowService;
use App\Services\SignhostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class WebhookController extends Controller
{
    public function mollie(
        Request $request,
        MollieService $mollie,
        SellerIntakeWorkflowService $sellerIntakeWorkflow
    ) {
        if ($this->isRateLimited($request, 'mollie')) {
            return response()->json(['message' => 'Rate limit exceeded'], 429);
        }

        if (! $this->verifyWebhookTimestamp($request, 'mollie')) {
            Log::warning('Mollie webhook timestamp too old');
            return response()->json(['message' => 'Stale webhook'], 400);
        }

        $paymentId = $request->input('id') ?? $request->input('payment_id');
        if (! $paymentId) {
            return response()->json(['message' => 'Missing payment id'], 200);
        }

        try {
            $remote = $mollie->getPayment((string) $paymentId);
        } catch (\RuntimeException $e) {
            Log::error('Mollie webhook fetch failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Payment fetch failed'], 200);
        }

        $metadata = is_array($remote['metadata'] ?? null) ? $remote['metadata'] : [];
        if (($metadata['payment_type'] ?? null) !== 'seller_listing_intake') {
            return response()->json(['message' => 'Ignored payment type'], 200);
        }

        $payment = BoatIntakePayment::query()
            ->where('mollie_payment_id', $paymentId)
            ->latest('id')
            ->first();

        if (! $payment && isset($metadata['boat_intake_id'])) {
            $payment = BoatIntakePayment::query()
                ->where('boat_intake_id', $metadata['boat_intake_id'])
                ->latest('id')
                ->first();
        }

        if (! $payment) {
            Log::warning('Mollie seller intake payment not found', [
                'payment_id' => $paymentId,
                'metadata' => $metadata,
            ]);

            return response()->json(['message' => 'Payment not found'], 200);
        }

        $sellerIntakeWorkflow->handlePaymentStatus(
            $payment,
            $this->normalizeMollieStatus((string) ($remote['status'] ?? 'open'))
        );

        return response()->json(['message' => 'ok'], 200);
    }

    public function signhost(Request $request)
    {
        if ($this->isRateLimited($request, 'signhost')) {
            return response()->json(['message' => 'Rate limit exceeded'], 429);
        }

        if (! $this->verifyWebhookTimestamp($request, 'signhost')) {
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
                return response()->json(['message' => 'Invalid auth'], 200);
            }
        }

        $signhost = new SignhostService();
        if (! $checksum || ! $signhost->verifyWebhook($payload, $checksum)) {
            Log::warning('Signhost webhook invalid checksum');
            return response()->json(['message' => 'Invalid checksum'], 200);
        }

        $transactionId = $payload['TransactionId'] ?? $payload['transactionId'] ?? null;
        $status = $payload['Status'] ?? $payload['status'] ?? null;
        if (! $transactionId || ! $status) {
            return response()->json(['message' => 'Missing data'], 200);
        }

        $eventKey = 'signhost:'.$transactionId.':'.$status;
        $idempotencyKey = $request->header('X-Webhook-Id')
            ?? $request->header('X-Event-Id')
            ?? $request->header('Idempotency-Key')
            ?? $eventKey;

        $claim = $this->claimWebhookEvent('signhost', $eventKey, $idempotencyKey, $payload);
        if ($claim['status'] === 'processed') {
            return response()->json(['message' => 'Already processed'], 200);
        }
        if ($claim['status'] === 'processing') {
            return response()->json(['message' => 'Already processing'], 200);
        }

        $event = $claim['event'];
        ProcessSignhostWebhookJob::dispatch($event->id);

        return response()->json(['message' => 'queued'], 200);
    }

    private function isRateLimited(Request $request, string $provider): bool
    {
        $limit = (int) config('security.webhooks.rate_limit_per_minute', 120);
        $key = 'webhook:'.$provider.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('Webhook rate limit exceeded', ['provider' => $provider, 'ip' => $request->ip()]);
            return true;
        }

        RateLimiter::hit($key, 60);
        return false;
    }

    private function verifyWebhookTimestamp(Request $request, string $provider): bool
    {
        $maxAge = (int) config('security.webhooks.max_age_seconds', 300);
        if ($maxAge <= 0) {
            return true;
        }

        $timestamp = $request->header('X-Webhook-Timestamp')
            ?? $request->header('X-Signature-Timestamp')
            ?? $request->header('X-Signhost-Timestamp')
            ?? $request->header('X-Mollie-Timestamp');

        if (! $timestamp) {
            return true;
        }

        $ts = is_numeric($timestamp) ? (int) $timestamp : strtotime((string) $timestamp);
        if (! $ts) {
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

    private function claimWebhookEvent(
        string $provider,
        string $eventKey,
        ?string $idempotencyKey,
        array $payload
    ): array {
        $lockSeconds = (int) config('security.webhooks.processing_lock_seconds', 300);

        return DB::transaction(function () use ($provider, $eventKey, $idempotencyKey, $payload, $lockSeconds) {
            $event = WebhookEvent::where('event_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if (! $event && $idempotencyKey) {
                $event = WebhookEvent::where('provider', $provider)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $event) {
                $event = WebhookEvent::create([
                    'provider' => $provider,
                    'event_key' => $eventKey,
                    'idempotency_key' => $idempotencyKey,
                    'payload_json' => $payload,
                    'processing_at' => null,
                    'processed_at' => null,
                ]);
            } else {
                $event->payload_json = $payload;
                $event->save();
            }

            if ($event->processed_at) {
                return ['status' => 'processed', 'event' => $event];
            }

            if ($event->processing_at && $event->processing_at->diffInSeconds(now()) < $lockSeconds) {
                return ['status' => 'processing', 'event' => $event];
            }

            $event->processing_at = now();
            $event->save();

            return ['status' => 'claimed', 'event' => $event];
        });
    }

    private function normalizeMollieStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid' => 'paid',
            'authorized' => 'authorized',
            'pending' => 'pending',
            'open' => 'open',
            'expired' => 'expired',
            'failed' => 'failed',
            'canceled', 'cancelled' => 'canceled',
            default => strtolower($status),
        };
    }
}
