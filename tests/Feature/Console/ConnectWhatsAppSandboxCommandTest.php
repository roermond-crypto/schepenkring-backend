<?php

use App\Models\HarborChannel;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('sandbox connect command creates a 360dialog channel and registers the webhook', function () {
    $location = Location::create([
        'name' => 'Command Harbor',
        'code' => 'CMD',
        'status' => 'ACTIVE',
    ]);

    config()->set('whatsapp.sandbox_base_url', 'https://waba-sandbox.360dialog.io');

    Http::fake([
        'https://waba-sandbox.360dialog.io/v1/configs/webhook' => Http::response([
            'message' => 'Webhook URL Set',
        ], 200),
    ]);

    $this->artisan('whatsapp:sandbox:connect', [
        'location_id' => $location->id,
        '--api-key' => 'sandbox-api-key',
        '--webhook-url' => 'https://example.test/api/webhooks/whatsapp/360dialog',
        '--token' => 'sandbox-token',
        '--from-number' => '551146733492',
        '--phone-number-id' => 'sandbox-pnid',
    ])->assertSuccessful();

    $channel = HarborChannel::query()
        ->where('harbor_id', $location->id)
        ->where('channel', 'whatsapp')
        ->where('provider', '360dialog')
        ->first();

    expect($channel)->not->toBeNull();
    expect($channel->webhook_token)->toBe('sandbox-token');
    expect($channel->metadata['sandbox'] ?? false)->toBeTrue();
    expect($channel->metadata['phone_number_id'] ?? null)->toBe('sandbox-pnid');

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://waba-sandbox.360dialog.io/v1/configs/webhook') {
            return false;
        }

        return $request->header('D360-API-KEY')[0] === 'sandbox-api-key'
            && data_get($request->data(), 'url') === 'https://example.test/api/webhooks/whatsapp/360dialog'
            && data_get($request->data(), 'headers.X-360D-Webhook-Token') === 'sandbox-token';
    });
});
