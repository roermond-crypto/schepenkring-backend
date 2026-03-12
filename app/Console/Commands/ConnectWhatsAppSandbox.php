<?php

namespace App\Console\Commands;

use App\Models\HarborChannel;
use App\Services\WhatsApp360DialogService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ConnectWhatsAppSandbox extends Command
{
    protected $signature = 'whatsapp:sandbox:connect
        {location_id : The location/harbor id that owns the sandbox channel}
        {--webhook-url= : Public webhook URL to register with 360dialog}
        {--api-key= : Sandbox API key. Falls back to WHATSAPP_360_SANDBOX_API_KEY}
        {--from-number= : Sandbox phone number shown in metadata}
        {--phone-number-id= : 360dialog phone number id for webhook matching}
        {--token= : Webhook token sent back by 360dialog}
        {--status=active : Channel status to store}';

    protected $description = 'Create or update a 360dialog sandbox channel and register its webhook URL.';

    public function handle(WhatsApp360DialogService $whatsApp): int
    {
        $locationId = (int) $this->argument('location_id');
        $apiKey = (string) ($this->option('api-key') ?: config('whatsapp.sandbox_api_key'));
        $webhookUrl = (string) ($this->option('webhook-url') ?: config('whatsapp.sandbox_webhook_url') ?: rtrim((string) config('app.url'), '/').'/api/webhooks/whatsapp/360dialog');
        $fromNumber = $this->normalizePhone((string) ($this->option('from-number') ?: config('whatsapp.sandbox_number')));
        $phoneNumberId = $this->option('phone-number-id');
        $token = (string) ($this->option('token') ?: Str::random(32));

        if ($apiKey === '') {
            $this->error('Missing sandbox API key. Pass --api-key or set WHATSAPP_360_SANDBOX_API_KEY.');

            return self::FAILURE;
        }

        if ($webhookUrl === '') {
            $this->error('Missing webhook URL. Pass --webhook-url or set WHATSAPP_360_SANDBOX_WEBHOOK_URL.');

            return self::FAILURE;
        }

        $channel = HarborChannel::query()->updateOrCreate([
            'harbor_id' => $locationId,
            'channel' => 'whatsapp',
            'provider' => '360dialog',
        ], [
            'from_number' => $fromNumber ?: null,
            'api_key_encrypted' => $apiKey,
            'webhook_token' => $token,
            'status' => (string) $this->option('status'),
            'metadata' => array_filter([
                'sandbox' => true,
                'base_url' => (string) config('whatsapp.sandbox_base_url'),
                'phone_number_id' => $phoneNumberId ?: null,
                'webhook_url' => $webhookUrl,
            ], static fn ($value) => $value !== null),
        ]);

        try {
            $response = $whatsApp->configureWebhook($channel, $webhookUrl, [
                'X-360D-Webhook-Token' => $token,
            ]);
        } catch (\Throwable $e) {
            $this->error('Webhook registration failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('360dialog sandbox connected.');
        $this->line('Channel ID: '.$channel->id);
        $this->line('Webhook URL: '.$webhookUrl);
        $this->line('Webhook token: '.$token);
        $this->line('Sandbox number: '.($fromNumber ?: (string) config('whatsapp.sandbox_number')));
        if ($phoneNumberId) {
            $this->line('Phone number ID: '.$phoneNumberId);
        }
        if ($response !== []) {
            $this->line('360dialog response: '.json_encode($response, JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }
}
