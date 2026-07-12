<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRetellWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Retell AI webhook ingestion — mirrors TelnyxVoiceWebhookController's
 * rate-limit / signature / idempotency structure so the two providers are
 * operationally consistent. Retell signs webhooks with HMAC-SHA256 over the
 * raw request body using the webhook secret from the Retell dashboard,
 * sent in an X-Retell-Signature header — verify this against Retell's
 * current webhook docs once real credentials exist; no live account was
 * available to confirm the exact header name/algorithm at write time.
 */
class RetellWebhookController extends Controller
{
    public function handle(Request $request)
    {
        if ($this->isRateLimited($request)) {
            return response()->json(['message' => 'Rate limit exceeded'], 429);
        }

        if (! $this->verifySignature($request)) {
            Log::warning('Retell webhook invalid signature');
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Retell webhook invalid signature', \Sentry\Severity::warning());
            }

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $eventType = $payload['event'] ?? null;
        $callId = data_get($payload, 'call.call_id');

        $eventKey = implode(':', array_filter([
            'retell',
            $callId ?: 'event',
            $eventType ?: 'unknown',
        ]));

        $idempotencyKey = $request->header('X-Retell-Event-Id') ?? $eventKey;

        $existing = WebhookEvent::where('idempotency_key', $idempotencyKey)->first()
            ?? WebhookEvent::where('event_key', $eventKey)->first();

        if ($existing && $existing->processed_at) {
            return response()->json(['message' => 'Already processed'], 200);
        }

        $event = $existing ?: WebhookEvent::create([
            'provider' => 'retell',
            'event_key' => $eventKey,
            'idempotency_key' => $idempotencyKey,
            'payload_json' => $payload,
            'processed_at' => null,
        ]);

        ProcessRetellWebhook::dispatch($event->id);

        return response()->json(['message' => 'ok'], 200);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = (string) config('services.retell.webhook_secret');
        if ($secret === '') {
            return true;
        }

        $signature = $request->header('X-Retell-Signature');
        if (! $signature) {
            return false;
        }

        $computed = hash_hmac('sha256', (string) $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }

    private function isRateLimited(Request $request): bool
    {
        $limit = (int) config('security.webhooks.rate_limit_per_minute', 120);
        $key = 'webhook:retell:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('Retell webhook rate limit exceeded', ['ip' => $request->ip()]);

            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }
}
