<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSignhostWebhookJob;
use App\Models\WebhookEvent;
use App\Services\SignhostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class WebhookController extends Controller
{
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
            ?? $request->header('X-Signhost-Timestamp');

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
}
