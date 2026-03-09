<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Jobs\InitiateOutboundCall;
use App\Jobs\ProcessTelnyxWebhook;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\HarborChannel;
use App\Models\Location;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

test('360dialog webhook matches NautiSecure route and response', function () {
    Queue::fake();

    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $channel = HarborChannel::create([
        'harbor_id' => $location->id,
        'channel' => 'whatsapp',
        'provider' => '360dialog',
        'from_number' => '31612345678',
        'webhook_token' => 'secret-token',
        'status' => 'active',
    ]);

    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'metadata' => [
                        'display_phone_number' => '31612345678',
                        'phone_number_id' => 'pnid-1',
                    ],
                ],
            ]],
        ]],
    ];

    $response = $this->withHeaders([
        'X-Webhook-Token' => 'secret-token',
    ])->postJson('/api/webhooks/whatsapp/360dialog', $payload);

    $response->assertOk()
        ->assertJson(['message' => 'ok']);

    Queue::assertPushed(ProcessWhatsAppWebhook::class, function (ProcessWhatsAppWebhook $job) use ($channel) {
        return $job->harborChannelId === $channel->id;
    });
});

test('telnyx webhook matches NautiSecure route and response', function () {
    Queue::fake();

    config()->set('services.telnyx.webhook_public_key', '');
    config()->set('services.telnyx.webhook_secret', '');

    $payload = [
        'data' => [
            'id' => 'evt_123',
            'event_type' => 'call.initiated',
            'payload' => [
                'call_control_id' => 'call_ctrl_123',
                'direction' => 'inbound',
                'from' => '+31611111111',
                'to' => '+31622222222',
            ],
        ],
    ];

    $response = $this->withHeaders([
        'X-Telnyx-Timestamp' => (string) now()->getTimestamp(),
    ])->postJson('/api/webhooks/telnyx/voice', $payload);

    $response->assertOk()
        ->assertJson(['message' => 'ok']);

    expect(WebhookEvent::where('provider', 'telnyx')->count())->toBe(1);

    Queue::assertPushed(ProcessTelnyxWebhook::class);
});

test('chat api queues outbound whatsapp messages like NautiSecure', function () {
    Queue::fake();

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $contact = Contact::create([
        'name' => 'Visitor',
        'whatsapp_user_id' => '31699999999',
        'consent_service_messages' => true,
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'contact_id' => $contact->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->postJson("/api/chat/conversations/{$conversation->id}/messages", [
        'text' => 'Hello via WhatsApp',
        'channel' => 'whatsapp',
    ]);

    $response->assertCreated()
        ->assertJsonPath('channel', 'whatsapp')
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('sender_type', 'admin');

    Queue::assertPushed(SendWhatsAppMessage::class);
});

test('chat api queues outbound call messages like NautiSecure', function () {
    Queue::fake();

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $location = Location::create([
        'name' => 'Den Haag Harbor',
        'code' => 'DHA',
        'status' => 'ACTIVE',
    ]);

    $contact = Contact::create([
        'name' => 'Caller',
        'phone' => '+31688888888',
        'consent_service_messages' => true,
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'contact_id' => $contact->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->postJson("/api/chat/conversations/{$conversation->id}/messages", [
        'message_type' => 'call',
        'channel' => 'phone',
        'metadata' => [
            'to_number' => '+31688888888',
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('message_type', 'call')
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('sender_type', 'admin');

    Queue::assertPushed(InitiateOutboundCall::class);
});
