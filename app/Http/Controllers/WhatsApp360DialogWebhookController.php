<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\HarborChannel;
use Illuminate\Http\Request;

class WhatsApp360DialogWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        $channel = $this->resolveHarborChannel($request, $payload);
        if (!$channel) {
            return response()->json(['message' => 'Unauthorized webhook.'], 401);
        }

        $token = $request->header('X-Webhook-Token')
            ?? $request->header('X-360D-Webhook-Token')
            ?? $request->header('X-Chat-Adapter-Secret');
        if ($channel->webhook_token && $channel->webhook_token !== $token) {
            return response()->json(['message' => 'Unauthorized webhook.'], 401);
        }

        ProcessWhatsAppWebhook::dispatch($channel->id, $payload);

        return response()->json(['message' => 'ok'], 200);
    }

    private function resolveHarborChannel(Request $request, array $payload): ?HarborChannel
    {
        $token = $request->header('X-Webhook-Token')
            ?? $request->header('X-360D-Webhook-Token')
            ?? $request->header('X-Chat-Adapter-Secret');

        if ($token) {
            $match = HarborChannel::where('channel', 'whatsapp')
                ->where('provider', '360dialog')
                ->where('webhook_token', $token)
                ->where('status', 'active')
                ->first();
            if ($match) {
                return $match;
            }
        }

        $metadata = $this->extractMetadata($payload);
        $displayNumber = $metadata['display_phone_number'] ?? null;
        $phoneNumberId = $metadata['phone_number_id'] ?? null;

        if (!$displayNumber && !$phoneNumberId) {
            return null;
        }

        $query = HarborChannel::where('channel', 'whatsapp')
            ->where('provider', '360dialog')
            ->where('status', 'active');

        if ($displayNumber) {
            $query->where('from_number', $displayNumber);
        }

        if ($phoneNumberId) {
            $query->orWhere('metadata->phone_number_id', $phoneNumberId);
        }

        return $query->first();
    }

    private function extractMetadata(array $payload): array
    {
        $entry = $payload['entry'][0] ?? null;
        $change = $entry['changes'][0] ?? null;
        $value = $change['value'] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return $value['metadata'] ?? [];
    }
}
