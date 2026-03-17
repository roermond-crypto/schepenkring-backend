<?php

use App\Enums\LocationRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Message;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('public lead creation stores an ai reply grounded in yacht and faq context and employees can see it', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.openai.chat_model', 'gpt-5-mini');

    $location = Location::create([
        'name' => 'Lelystad Marina',
        'code' => 'LEY',
        'status' => 'ACTIVE',
        'chat_widget_welcome_text' => 'Ask us anything about this yacht.',
    ]);

    $yacht = Yacht::create([
        'location_id' => $location->id,
        'boat_name' => 'Contest 42',
        'manufacturer' => 'Contest',
        'model' => '42CS',
        'year' => 2021,
        'price' => 265000,
        'status' => 'available',
        'location_city' => 'Lelystad',
        'external_url' => 'https://schepen-kring.nl/yachts/contest-42',
        'short_description_en' => 'A bluewater cruiser with a bright saloon and strong sailing package.',
    ]);

    $faq = Faq::create([
        'location_id' => $location->id,
        'question' => 'Can I insure my boat while it is stored in a marina?',
        'answer' => 'Boat insurance can remain valid while the vessel is stored in a marina as long as the policy conditions and marina safety requirements are met.',
        'category' => 'Insurance',
        'language' => 'en',
        'visibility' => 'internal',
        'source_type' => 'faq',
        'brand' => 'Contest',
        'model' => '42CS',
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_chat_1',
            'model' => 'gpt-5-mini',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'reply' => 'The Contest 42CS is a 2021 yacht in Lelystad listed at EUR 265,000, and marina storage can still be compatible with insurance when the policy conditions are met.',
                        'confidence' => 0.93,
                        'should_handoff' => false,
                        'handoff_reason' => '',
                        'used_sources' => [
                            'yacht:' . $yacht->id,
                            'faq:' . $faq->id,
                            'location:' . $location->id,
                        ],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 400,
                'output_tokens' => 90,
                'total_tokens' => 490,
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/public/leads', [
        'location_id' => $location->id,
        'source_url' => $yacht->external_url,
        'name' => 'Peter Client',
        'email' => 'peter@example.test',
        'message' => 'Can I insure my boat while it is stored in a marina?',
        'client_message_id' => 'lead-ai-1',
        'visitor_id' => 'visitor-peter-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message.sender_type', 'visitor')
        ->assertJsonPath('ai_message.sender_type', 'ai')
        ->assertJsonPath('ai_message.metadata.provider', 'openai')
        ->assertJsonPath('conversation.location_id', $location->id)
        ->assertJsonPath('conversation.boat_id', $yacht->id);

    $conversationId = $response->json('conversation.id');
    $visitorMessageId = $response->json('message.id');
    $aiMessageId = $response->json('ai_message.id');

    expect(Message::query()->where('conversation_id', $conversationId)->count())->toBe(2);
    expect(Message::query()->whereKey($aiMessageId)->value('sender_type'))->toBe('ai');
    expect(data_get(Message::query()->find($aiMessageId)?->metadata, 'in_reply_to_message_id'))->toBe($visitorMessageId);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $location->users()->attach($employee->id, [
        'role' => LocationRole::LOCATION_EMPLOYEE->value,
    ]);

    Sanctum::actingAs($employee);

    $this->getJson("/api/chat/conversations/{$conversationId}")
        ->assertOk()
        ->assertJsonPath('location.id', $location->id)
        ->assertJsonPath('messages.0.sender_type', 'visitor')
        ->assertJsonPath('messages.1.sender_type', 'ai');

    Http::assertSent(function ($request) use ($location, $yacht) {
        if ($request->url() !== 'https://api.openai.com/v1/responses') {
            return false;
        }

        $payload = (string) data_get($request->data(), 'input.0.content.0.text');

        return str_contains($payload, 'Contest 42')
            && str_contains($payload, 'Boat insurance can remain valid while the vessel is stored in a marina')
            && str_contains($payload, '"location_id":' . $location->id)
            && str_contains($payload, (string) $yacht->price);
    });
});

test('public conversation message deduplicates and returns the existing ai reply', function () {
    config()->set('services.openai.key', 'test-openai');

    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $contact = Contact::create([
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'consent_service_messages' => true,
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'contact_id' => $contact->id,
        'visitor_id' => 'visitor-amsterdam-1',
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'ai_mode' => 'auto',
        'status' => 'open',
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_chat_2',
            'model' => 'gpt-5-mini',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'reply' => 'I can help with that. Please tell me what details you want to know about the yacht.',
                        'confidence' => 0.7,
                        'should_handoff' => false,
                        'handoff_reason' => '',
                        'used_sources' => ['location:' . $location->id],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
        ], 200),
    ]);

    $first = $this->postJson("/api/public/conversations/{$conversation->id}/messages", [
        'body' => 'Can you help me with this yacht?',
        'client_message_id' => 'dup-msg-1',
        'visitor_id' => 'visitor-amsterdam-1',
    ]);

    $first->assertCreated()
        ->assertJsonPath('message.sender_type', 'visitor')
        ->assertJsonPath('ai_message.sender_type', 'ai');

    $messageId = $first->json('message.id');
    $aiMessageId = $first->json('ai_message.id');

    $duplicate = $this->postJson("/api/public/conversations/{$conversation->id}/messages", [
        'body' => 'Can you help me with this yacht?',
        'client_message_id' => 'dup-msg-1',
        'visitor_id' => 'visitor-amsterdam-1',
    ]);

    $duplicate->assertOk()
        ->assertJsonPath('message.id', $messageId)
        ->assertJsonPath('ai_message.id', $aiMessageId);

    expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(2);
});

test('generic public chat message endpoint also returns a stored ai reply for visitors', function () {
    config()->set('services.openai.key', 'test-openai');

    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'ai_mode' => 'auto',
        'status' => 'open',
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_chat_3',
            'model' => 'gpt-5-mini',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'reply' => 'Hello. I can help with questions about this location and any linked yacht details.',
                        'confidence' => 0.82,
                        'should_handoff' => false,
                        'handoff_reason' => '',
                        'used_sources' => ['location:' . $location->id],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
        ], 200),
    ]);

    $response = $this->postJson("/api/chat/conversations/{$conversation->id}/messages", [
        'text' => 'hello can someone help me?',
        'visitor_id' => 'visitor-rotterdam-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('sender_type', 'visitor')
        ->assertJsonPath('ai_message.sender_type', 'ai')
        ->assertJsonPath('ai_message.metadata.provider', 'openai');

    expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(2);
});
