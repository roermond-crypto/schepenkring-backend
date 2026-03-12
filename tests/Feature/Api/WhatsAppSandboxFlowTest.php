<?php

use App\Jobs\ProcessWhatsAppWebhook;
use App\Jobs\SendWhatsAppMessage;
use App\Models\ChannelIdentity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\HarborChannel;
use App\Models\Location;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('sandbox outbound send, inbound reply, and status webhooks complete the whatsapp flow', function () {
    $location = Location::create([
        'name' => 'Sandbox Marina',
        'code' => 'SBX',
        'status' => 'ACTIVE',
    ]);

    $contact = Contact::create([
        'name' => 'Sandbox User',
        'phone' => '31677777777',
        'whatsapp_user_id' => '31677777777',
        'consent_service_messages' => true,
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'contact_id' => $contact->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
        'last_inbound_at' => now(),
        'window_expires_at' => now()->addHours(24),
    ]);

    $channel = HarborChannel::create([
        'harbor_id' => $location->id,
        'channel' => 'whatsapp',
        'provider' => '360dialog',
        'from_number' => '551146733492',
        'api_key_encrypted' => 'sandbox-api-key',
        'webhook_token' => 'sandbox-token',
        'status' => 'active',
        'metadata' => [
            'sandbox' => true,
            'base_url' => 'https://waba-sandbox.360dialog.io',
            'phone_number_id' => 'sandbox-pnid',
        ],
    ]);

    $outbound = Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'admin',
        'text' => 'Hello from sandbox',
        'body' => 'Hello from sandbox',
        'channel' => 'whatsapp',
        'message_type' => 'text',
        'status' => 'queued',
        'delivery_state' => 'queued',
    ]);

    Http::fake([
        'https://waba-sandbox.360dialog.io/v1/messages' => Http::response([], 200),
    ]);

    (new SendWhatsAppMessage($outbound->id))->handle(app(\App\Services\WhatsApp360DialogService::class));

    $outbound->refresh();

    expect($outbound->status)->toBe('sent');
    expect($outbound->delivery_state)->toBe('sent');
    expect($outbound->external_message_id)->toBeNull();
    expect(ChannelIdentity::query()
        ->where('conversation_id', $conversation->id)
        ->where('type', 'whatsapp')
        ->where('external_thread_id', 'whatsapp:'.$location->id.':31677777777')
        ->exists())->toBeTrue();

    $inboundPayload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'metadata' => [
                        'display_phone_number' => '551146733492',
                        'phone_number_id' => 'sandbox-pnid',
                    ],
                    'contacts' => [[
                        'wa_id' => '31677777777',
                        'profile' => ['name' => 'Sandbox User'],
                    ]],
                    'messages' => [[
                        'from' => '31677777777',
                        'id' => 'wamid.inbound.1',
                        'timestamp' => '1710000000',
                        'type' => 'text',
                        'text' => ['body' => 'Reply from phone'],
                        'context' => ['id' => 'wamid.outbound.1'],
                    ]],
                ],
            ]],
        ]],
    ];

    (new ProcessWhatsAppWebhook($channel->id, $inboundPayload))->handle(
        app(\App\Services\ChatConversationService::class),
        app(\App\Services\WhatsApp360DialogService::class)
    );

    $inboundMessage = Message::query()->where('external_message_id', 'wamid.inbound.1')->first();

    expect($inboundMessage)->not->toBeNull();
    expect($inboundMessage->conversation_id)->toBe($conversation->id);
    expect($inboundMessage->status)->toBe('received');
    expect($inboundMessage->delivery_state)->toBe('received');
    expect(Conversation::query()->count())->toBe(1);

    $statusPayload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'metadata' => [
                        'display_phone_number' => '551146733492',
                        'phone_number_id' => 'sandbox-pnid',
                    ],
                    'statuses' => [[
                        'id' => 'wamid.outbound.1',
                        'status' => 'sent',
                        'timestamp' => '1710000001',
                        'recipient_id' => '31677777777',
                    ]],
                ],
            ]],
        ]],
    ];

    (new ProcessWhatsAppWebhook($channel->id, $statusPayload))->handle(
        app(\App\Services\ChatConversationService::class),
        app(\App\Services\WhatsApp360DialogService::class)
    );

    $deliveredPayload = $statusPayload;
    $deliveredPayload['entry'][0]['changes'][0]['value']['statuses'][0]['status'] = 'delivered';
    $deliveredPayload['entry'][0]['changes'][0]['value']['statuses'][0]['timestamp'] = '1710000002';

    (new ProcessWhatsAppWebhook($channel->id, $deliveredPayload))->handle(
        app(\App\Services\ChatConversationService::class),
        app(\App\Services\WhatsApp360DialogService::class)
    );

    $readPayload = $statusPayload;
    $readPayload['entry'][0]['changes'][0]['value']['statuses'][0]['status'] = 'read';
    $readPayload['entry'][0]['changes'][0]['value']['statuses'][0]['timestamp'] = '1710000003';

    (new ProcessWhatsAppWebhook($channel->id, $readPayload))->handle(
        app(\App\Services\ChatConversationService::class),
        app(\App\Services\WhatsApp360DialogService::class)
    );

    expect($outbound->fresh()->external_message_id)->toBe('wamid.outbound.1');
    expect($outbound->fresh()->status)->toBe('read');
    expect($outbound->fresh()->delivery_state)->toBe('read');
    expect($outbound->fresh()->delivered_at?->timestamp)->toBe(1710000002);
    expect($outbound->fresh()->read_at?->timestamp)->toBe(1710000003);
});

test('send job builds a template payload for whatsapp sandbox messages', function () {
    $location = Location::create([
        'name' => 'Template Harbor',
        'code' => 'TMP',
        'status' => 'ACTIVE',
    ]);

    $contact = Contact::create([
        'name' => 'Template User',
        'phone' => '31688888888',
        'whatsapp_user_id' => '31688888888',
        'consent_service_messages' => true,
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'contact_id' => $contact->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
    ]);

    HarborChannel::create([
        'harbor_id' => $location->id,
        'channel' => 'whatsapp',
        'provider' => '360dialog',
        'from_number' => '551146733492',
        'api_key_encrypted' => 'sandbox-api-key',
        'webhook_token' => 'sandbox-token',
        'status' => 'active',
        'metadata' => [
            'sandbox' => true,
            'base_url' => 'https://waba-sandbox.360dialog.io',
            'phone_number_id' => 'sandbox-pnid',
        ],
    ]);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'admin',
        'text' => 'Template placeholder',
        'body' => 'Template placeholder',
        'channel' => 'whatsapp',
        'message_type' => 'text',
        'status' => 'queued',
        'delivery_state' => 'queued',
        'metadata' => [
            'whatsapp' => [
                'template' => [
                    'name' => 'sandbox_template',
                    'language' => 'en_US',
                    'components' => [],
                ],
            ],
        ],
    ]);

    Http::fake([
        'https://waba-sandbox.360dialog.io/v1/messages' => Http::response([
            'messages' => [[
                'id' => 'wamid.template.1',
            ]],
        ], 200),
    ]);

    (new SendWhatsAppMessage($message->id))->handle(app(\App\Services\WhatsApp360DialogService::class));

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://waba-sandbox.360dialog.io/v1/messages') {
            return false;
        }

        return data_get($request->data(), 'type') === 'template'
            && data_get($request->data(), 'template.name') === 'sandbox_template'
            && data_get($request->data(), 'template.language.code') === 'en_US';
    });

    expect($message->fresh()->external_message_id)->toBe('wamid.template.1');
});
