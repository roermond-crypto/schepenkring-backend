<?php

use App\Contracts\VoiceProvider;
use App\Jobs\ProcessRetellWebhook;
use App\Models\CallSession;
use App\Models\Conversation;
use App\Models\HarborChannel;
use App\Models\Location;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\Voice\RetellVoiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('voice provider interface resolves to RetellVoiceProvider by default', function () {
    expect(app(VoiceProvider::class))->toBeInstanceOf(RetellVoiceProvider::class);
    expect(app(VoiceProvider::class)->name())->toBe('retell');
});

test('retell webhook matches the route and response shape used by the telnyx webhook', function () {
    Queue::fake();

    config()->set('services.retell.webhook_secret', '');

    $payload = [
        'event' => 'call_started',
        'call' => [
            'call_id' => 'call_abc123',
            'direction' => 'outbound',
            'from_number' => '+31622222222',
            'to_number' => '+31611111111',
            'agent_id' => 'agent_seller_outbound_nl',
        ],
    ];

    $response = $this->postJson('/api/webhooks/retell', $payload);

    $response->assertOk()->assertJson(['message' => 'ok']);

    expect(WebhookEvent::where('provider', 'retell')->count())->toBe(1);

    Queue::assertPushed(ProcessRetellWebhook::class);
});

test('retell webhook is idempotent on a repeated event', function () {
    Queue::fake();
    config()->set('services.retell.webhook_secret', '');

    $payload = [
        'event' => 'call_ended',
        'call' => ['call_id' => 'call_dupe_1'],
    ];

    $first = $this->postJson('/api/webhooks/retell', $payload);
    $first->assertOk();

    $event = WebhookEvent::where('provider', 'retell')->first();
    $event->update(['processed_at' => now()]);

    $second = $this->postJson('/api/webhooks/retell', $payload);
    $second->assertOk()->assertJson(['message' => 'Already processed']);

    expect(WebhookEvent::where('provider', 'retell')->count())->toBe(1);
});

test('retell webhook rejects an invalid signature when a secret is configured', function () {
    config()->set('services.retell.webhook_secret', 'test-secret');

    $response = $this->withHeaders(['X-Retell-Signature' => 'not-the-real-signature'])
        ->postJson('/api/webhooks/retell', ['event' => 'call_started', 'call' => ['call_id' => 'call_x']]);

    $response->assertStatus(401);
});

test('retell webhook accepts a correctly signed payload', function () {
    config()->set('services.retell.webhook_secret', 'test-secret');

    $payload = ['event' => 'call_started', 'call' => ['call_id' => 'call_signed_1']];
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'test-secret');

    $response = $this->withHeaders(['X-Retell-Signature' => $signature])
        ->call('POST', '/api/webhooks/retell', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $body);

    $response->assertOk();
});

test('RetellVoiceProvider parses call_id from a successful create-phone-call response', function () {
    config()->set('services.retell.api_key', 'test-key');

    Http::fake([
        'api.retellai.com/v2/create-phone-call' => Http::response(['call_id' => 'call_new_1', 'call_status' => 'registered'], 200),
    ]);

    $provider = app(RetellVoiceProvider::class);
    $result = $provider->initiateOutboundCall([
        'from' => '+31611111111',
        'to' => '+31622222222',
        'agent_id' => 'agent_1',
        'dynamic_variables' => ['yacht_name' => 'Bayliner 175 GT'],
    ]);

    expect($result['external_call_id'])->toBe('call_new_1');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.retellai.com/v2/create-phone-call'
            && $request['from_number'] === '+31611111111'
            && $request['override_agent_id'] === 'agent_1';
    });
});

test('handleRetellCallAnalyzed stores transcript and structured analysis on the call session', function () {
    $location = Location::create(['name' => 'Roermond Marina', 'code' => 'RMD', 'status' => 'ACTIVE']);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'status' => 'open',
        'channel' => 'phone',
        'channel_origin' => 'phone',
    ]);

    $session = CallSession::create([
        'conversation_id' => $conversation->id,
        'harbor_id' => $location->id,
        'direction' => 'outbound',
        'status' => 'ended',
        'provider' => 'retell',
        'external_call_id' => 'call_analyzed_1',
        'call_control_id' => 'call_analyzed_1',
        'started_at' => now()->subMinutes(2),
        'ended_at' => now(),
    ]);

    $phoneService = app(\App\Services\PhoneCallService::class);
    $phoneService->handleRetellCallAnalyzed([
        'call_id' => 'call_analyzed_1',
        'transcript' => 'Agent: Hello... Seller: I would like to sell my boat.',
        'recording_url' => 'https://example.com/recording.mp3',
        'call_analysis' => [
            'call_summary' => 'Seller wants to list a Bayliner 175 GT.',
            'call_successful' => true,
            'custom_analysis_data' => [
                'outcome' => 'seller_onboarding_link_requested',
                'callback_requested' => false,
            ],
        ],
    ]);

    $session->refresh();

    expect($session->transcript_text)->toContain('sell my boat');
    expect($session->analysis['call_summary'])->toBe('Seller wants to list a Bayliner 175 GT.');
    expect($session->analysis['custom_analysis_data']['outcome'])->toBe('seller_onboarding_link_requested');

    expect(Message::where('conversation_id', $conversation->id)
        ->where('message_type', 'call_transcript')
        ->exists())->toBeTrue();
});

test('inbound calls resolve the harbor channel matching the active voice provider, not telnyx', function () {
    $location = Location::create(['name' => 'Heeg Marina', 'code' => 'HEG', 'status' => 'ACTIVE']);

    // A dormant Telnyx channel on the SAME number would previously have been
    // the only thing PhoneCallService looked for (hardcoded 'telnyx') — it
    // must now be ignored in favor of the retell-provider row.
    HarborChannel::create([
        'harbor_id' => $location->id,
        'channel' => 'phone',
        'provider' => 'telnyx',
        'from_number' => '+31688888888',
        'status' => 'active',
    ]);

    HarborChannel::create([
        'harbor_id' => $location->id,
        'channel' => 'phone',
        'provider' => 'retell',
        'from_number' => '+31688888888',
        'status' => 'active',
    ]);

    app(\App\Services\PhoneCallService::class)->handleRetellCallStarted([
        'call_id' => 'call_inbound_1',
        'direction' => 'inbound',
        'from_number' => '+31611112222',
        'to_number' => '+31688888888',
    ]);

    $session = CallSession::where('external_call_id', 'call_inbound_1')->firstOrFail();

    expect($session->harbor_id)->toBe($location->id);
    expect($session->status)->toBe('ringing');
});
