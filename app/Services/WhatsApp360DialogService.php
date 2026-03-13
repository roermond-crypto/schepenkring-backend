<?php

namespace App\Services;

use App\Models\HarborChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsApp360DialogService
{
    public function sendMessage(HarborChannel $channel, array $payload): array
    {
        $response = $this->request(
            $channel,
            (string) config('whatsapp.messages_path'),
            $payload
        );

        if ($response->failed()) {
            Log::error('WhatsApp send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('WhatsApp send failed.');
        }

        return $response->json() ?? [];
    }

    public function configureWebhook(HarborChannel $channel, string $url, array $headers = []): array
    {
        $payload = ['url' => $url];

        if ($headers !== []) {
            $payload['headers'] = $headers;
        }

        $response = $this->request(
            $channel,
            (string) config('whatsapp.webhook_config_path'),
            $payload
        );

        if ($response->failed()) {
            Log::error('WhatsApp webhook configuration failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('WhatsApp webhook configuration failed.');
        }

        return $response->json() ?? ['success' => true];
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

    private function request(HarborChannel $channel, string $path, array $payload)
    {
        $apiKey = $channel->apiKey();
        if (! $apiKey) {
            throw new \RuntimeException('Missing WhatsApp API key.');
        }

        $baseUrl = rtrim($this->baseUrl($channel), '/');
        $url = $baseUrl.(string) $path;

        return Http::acceptJson()
            ->withHeaders(['D360-API-KEY' => $apiKey])
            ->post($url, $payload);
    }

    private function baseUrl(HarborChannel $channel): string
    {
        $metadata = $channel->metadata ?? [];

        if (! empty($metadata['base_url']) && is_string($metadata['base_url'])) {
            return $metadata['base_url'];
        }

        if (! empty($metadata['sandbox'])) {
            return (string) config('whatsapp.sandbox_base_url');
        }

        return (string) config('whatsapp.base_url');
    }
}
