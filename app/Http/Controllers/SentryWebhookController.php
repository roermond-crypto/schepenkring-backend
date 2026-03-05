<?php

namespace App\Http\Controllers;

use App\Services\SentryIssueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SentryWebhookController extends Controller
{
    public function handle(Request $request, SentryIssueService $service)
    {
        $secret = env('SENTRY_WEBHOOK_SECRET');
        if ($secret) {
            $signature = $request->header('X-Sentry-Signature');
            $payload = $request->getContent();
            $expected = hash_hmac('sha256', $payload, $secret);
            if (!$signature || !hash_equals($expected, $signature)) {
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        }

        try {
            $data = $request->all();
            $service->upsertFromWebhook($data);
        } catch (\Exception $e) {
            Log::error('Sentry webhook failed: ' . $e->getMessage());
            return response()->json(['message' => 'Webhook processing failed'], 500);
        }

        return response()->json(['status' => 'ok']);
    }
}
