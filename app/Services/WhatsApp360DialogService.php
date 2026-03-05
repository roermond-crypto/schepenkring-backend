<?php

namespace App\Services;

use App\Models\HarborChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsApp360DialogService
{
    public function sendMessage(HarborChannel $channel, array $payload): array
    {
        $apiKey = $channel->apiKey();
        if (!$apiKey) {
            throw new \RuntimeException('Missing WhatsApp API key.');
        }

        $baseUrl = rtrim((string) config('whatsapp.base_url'), '/');
        $path = (string) config('whatsapp.messages_path');
        $url = $baseUrl . $path;

        $response = Http::acceptJson()
            ->withHeaders(['D360-API-KEY' => $apiKey])
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('WhatsApp send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('WhatsApp send failed.');
        }

        return $response->json() ?? [];
    }

    public function extractInboundMessages(array $payload): array
    {
        $messages = [];
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['messages'] ?? [] as $message) {
                    $messages[] = [
                        'message' => $message,
                        'metadata' => $value['metadata'] ?? [],
                        'contacts' => $value['contacts'] ?? [],
                    ];
                }
            }
        }

        return $messages;
    }

    public function extractStatuses(array $payload): array
    {
        $statuses = [];
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['statuses'] ?? [] as $status) {
                    $statuses[] = [
                        'status' => $status,
                        'metadata' => $value['metadata'] ?? [],
                    ];
                }
            }
        }

        return $statuses;
    }
}
