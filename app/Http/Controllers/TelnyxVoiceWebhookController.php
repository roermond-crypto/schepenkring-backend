<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelnyxWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class TelnyxVoiceWebhookController extends Controller
{
    public function handle(Request $request)
    {
        if ($this->isRateLimited($request)) {
            return response()->json(['message' => 'Rate limit exceeded'], 429);
        }

        if (!$this->verifyWebhookTimestamp($request)) {
            Log::warning('Telnyx webhook timestamp too old');
            return response()->json(['message' => 'Stale webhook'], 400);
        }

        if (!$this->verifySignature($request)) {
            Log::warning('Telnyx webhook invalid signature');
            if (function_exists('\\Sentry\\captureMessage')) {
                \Sentry\captureMessage('Telnyx webhook invalid signature', \Sentry\Severity::warning());
            }
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $eventType = data_get($payload, 'data.event_type') ?? data_get($payload, 'event_type');
        $eventId = data_get($payload, 'data.id') ?? data_get($payload, 'id');
        $callControlId = data_get($payload, 'data.payload.call_control_id')
            ?? data_get($payload, 'payload.call_control_id')
            ?? data_get($payload, 'call_control_id');

        $eventKeyParts = array_filter([
            'telnyx',
            $callControlId ?: 'event',
            $eventType ?: 'unknown',
            $eventId,
        ]);
        $eventKey = implode(':', $eventKeyParts);

        $idempotencyKey = $request->header('X-Webhook-Id')
            ?? $request->header('X-Event-Id')
            ?? $eventId
            ?? $eventKey;

        $existing = WebhookEvent::where('idempotency_key', $idempotencyKey)->first()
            ?? WebhookEvent::where('event_key', $eventKey)->first();

        if ($existing && $existing->processed_at) {
            return response()->json(['message' => 'Already processed'], 200);
        }

        $event = $existing ?: WebhookEvent::create([
            'provider' => 'telnyx',
            'event_key' => $eventKey,
            'idempotency_key' => $idempotencyKey,
            'payload_json' => $payload,
            'processed_at' => null,
        ]);

        ProcessTelnyxWebhook::dispatch($event->id);

        return response()->json(['message' => 'ok'], 200);
    }

    private function verifySignature(Request $request): bool
    {
        $publicKey = (string) config('services.telnyx.webhook_public_key');
        $secret = (string) config('services.telnyx.webhook_secret');

        if ($publicKey === '' && $secret === '') {
            return true;
        }

        if ($publicKey !== '') {
            $signature = $request->header('X-Telnyx-Signature-Ed25519');
            $timestamp = $request->header('X-Telnyx-Timestamp');
            if (!$signature || !$timestamp) {
                return false;
            }

            if (!function_exists('sodium_crypto_sign_verify_detached')) {
                return false;
            }

            $payload = $timestamp . $request->getContent();
            $decodedSignature = base64_decode($signature, true) ?: hex2bin($signature);
            $decodedKey = base64_decode($publicKey, true) ?: hex2bin($publicKey);

            if (!$decodedSignature || !$decodedKey) {
                return false;
            }

            return sodium_crypto_sign_verify_detached($decodedSignature, $payload, $decodedKey);
        }

        $signature = $request->header('X-Telnyx-Signature')
            ?? $request->header('X-Webhook-Signature');

        if (!$signature) {
            return false;
        }

        $computed = hash_hmac('sha256', (string) $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }

    private function verifyWebhookTimestamp(Request $request): bool
    {
        $maxAge = (int) config('security.webhooks.max_age_seconds', 300);
        if ($maxAge <= 0) {
            return true;
        }

        $timestamp = $request->header('X-Telnyx-Timestamp')
            ?? $request->header('X-Webhook-Timestamp')
            ?? $request->header('X-Signature-Timestamp');

        if (!$timestamp) {
            return true;
        }

        $ts = is_numeric($timestamp) ? (int) $timestamp : strtotime((string) $timestamp);
        if (!$ts) {
            Log::warning('Telnyx webhook timestamp parse failed');
            return false;
        }

        $age = abs(now()->getTimestamp() - $ts);
        if ($age > $maxAge) {
            Log::warning('Telnyx webhook timestamp outside allowed window', ['age' => $age]);
            return false;
        }

        return true;
    }

    private function isRateLimited(Request $request): bool
    {
        $limit = (int) config('security.webhooks.rate_limit_per_minute', 120);
        $key = 'webhook:telnyx:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('Telnyx webhook rate limit exceeded', ['ip' => $request->ip()]);
            return true;
        }

        RateLimiter::hit($key, 60);
        return false;
    }
}
