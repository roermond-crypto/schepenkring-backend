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

beforeEach(function () {
    config()->set('services.chat_ai.provider', null);
    config()->set('services.openai.key', null);
    config()->set('services.gemini.key', null);
});

test('public lead creation stores an ai reply grounded in yacht and faq context and employees can see it', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.chat_ai.provider', 'openai');
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
        'visibility' => 'public',
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

test('public lead creation can use gemini for grounded website chat replies', function () {
    config()->set('services.openai.key', null);
    config()->set('services.gemini.key', 'test-gemini');
    config()->set('services.gemini.chat_model', 'gemini-2.5-flash');
    config()->set('services.chat_ai.provider', 'gemini');

    $location = Location::create([
        'name' => 'Lemmer Marina',
        'code' => 'LEM',
        'status' => 'ACTIVE',
    ]);

    $faq = Faq::create([
        'location_id' => $location->id,
        'question' => 'Do you offer berth assistance?',
        'answer' => 'Yes, berth assistance is available during opening hours at this marina.',
        'category' => 'Services',
        'language' => 'en',
        'visibility' => 'public',
        'source_type' => 'faq',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=test-gemini' => Http::response([
            'modelVersion' => 'gemini-2.5-flash',
            'responseId' => 'gemini-chat-1',
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode([
                            'reply' => 'Yes, berth assistance is available during opening hours at this marina.',
                            'confidence' => 0.87,
                            'should_handoff' => false,
                            'handoff_reason' => '',
                            'used_sources' => [
                                'faq:' . $faq->id,
                                'location:' . $location->id,
                            ],
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]],
                ],
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 321,
                'candidatesTokenCount' => 54,
                'totalTokenCount' => 375,
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/public/leads', [
        'location_id' => $location->id,
        'message' => 'Do you offer berth assistance?',
        'client_message_id' => 'public-gemini-1',
        'visitor_id' => 'visitor-gemini-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('ai_message.sender_type', 'ai')
        ->assertJsonPath('ai_message.metadata.provider', 'gemini')
        ->assertJsonPath('ai_message.metadata.model', 'gemini-2.5-flash')
        ->assertJsonPath('ai_message.metadata.response_id', 'gemini-chat-1');

    Http::assertSent(function ($request) use ($faq, $location) {
        if ($request->url() !== 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=test-gemini') {
            return false;
        }

        $payload = (string) data_get($request->data(), 'contents.0.parts.0.text');

        return str_contains($payload, 'Do you offer berth assistance?')
            && str_contains($payload, $faq->answer)
            && str_contains($payload, '"location":{"id":' . $location->id);
    });
});

test('public chat context includes related knowledge entities for the current yacht and harbor', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.chat_ai.provider', 'openai');
    config()->set('services.openai.chat_model', 'gpt-5-mini');

    $location = Location::create([
        'name' => 'Medemblik Marina',
        'code' => 'MED',
        'status' => 'ACTIVE',
        'chat_widget_welcome_text' => 'Ask NauticSecure AI about this harbor and yacht.',
    ]);

    $yacht = Yacht::create([
        'location_id' => $location->id,
        'boat_name' => 'Grand Soleil 40',
        'manufacturer' => 'Grand Soleil',
        'model' => '40',
        'year' => 2020,
        'status' => 'available',
        'price' => 289000,
        'location_city' => 'Medemblik',
        'external_url' => 'https://schepen-kring.nl/yachts/grand-soleil-40',
        'short_description_en' => 'Fast cruiser with a secure cockpit and responsive helm.',
    ]);

    Faq::create([
        'location_id' => $location->id,
        'question' => 'Is berth assistance available?',
        'answer' => 'Yes, berth assistance is available during opening hours.',
        'category' => 'Services',
        'language' => 'en',
        'visibility' => 'public',
        'source_type' => 'faq',
    ]);

    app(\App\Services\KnowledgeGraphService::class)->syncLocation($location);
    app(\App\Services\KnowledgeGraphService::class)->syncYacht($yacht);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_chat_related_entities',
            'model' => 'gpt-5-mini',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'reply' => 'Yes, berth assistance is available and this yacht is listed in Medemblik Marina.',
                        'confidence' => 0.89,
                        'should_handoff' => false,
                        'handoff_reason' => '',
                        'used_sources' => [
                            'yacht:' . $yacht->id,
                            'location:' . $location->id,
                        ],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 410,
                'output_tokens' => 80,
                'total_tokens' => 490,
            ],
        ], 200),
    ]);

    $this->postJson('/api/public/leads', [
        'location_id' => $location->id,
        'source_url' => $yacht->external_url,
        'name' => 'Mila Sailor',
        'email' => 'mila@example.test',
        'message' => 'Is berth assistance available?',
        'client_message_id' => 'lead-ai-related-1',
        'visitor_id' => 'visitor-related-1',
    ])->assertCreated();

    Http::assertSent(function ($request) use ($location, $yacht) {
        if ($request->url() !== 'https://api.openai.com/v1/responses') {
            return false;
        }

        $payload = (string) data_get($request->data(), 'input.0.content.0.text');

        return str_contains($payload, '"related_entities"')
            && str_contains($payload, '"source_ref":"yacht:' . $yacht->id . '"')
            && str_contains($payload, '"to_source_ref":"location:' . $location->id . '"')
            && str_contains($payload, $location->name)
            && str_contains($payload, '"type":"located_at"');
    });
});

test('public chat only grounds answers with public faq entries', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.chat_ai.provider', 'openai');
    config()->set('services.openai.chat_model', 'gpt-5-mini');

    $location = Location::create([
        'name' => 'Enkhuizen Harbor',
        'code' => 'ENK',
        'status' => 'ACTIVE',
    ]);

    $publicFaq = Faq::create([
        'location_id' => $location->id,
        'question' => 'Do you offer winter storage?',
        'answer' => 'Yes, winter storage is available for selected boats and depends on space at the harbor.',
        'category' => 'Storage',
        'language' => 'en',
        'visibility' => 'public',
        'source_type' => 'faq',
    ]);

    Faq::create([
        'location_id' => $location->id,
        'question' => 'Do you offer winter storage?',
        'answer' => 'Internal policy: only quote winter storage after staff approval.',
        'category' => 'Storage',
        'language' => 'en',
        'visibility' => 'internal',
        'source_type' => 'faq',
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_chat_public_only',
            'model' => 'gpt-5-mini',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'reply' => 'Yes, winter storage is available for selected boats and depends on space at the harbor.',
                        'confidence' => 0.88,
                        'should_handoff' => false,
                        'handoff_reason' => '',
                        'used_sources' => [
                            'faq:' . $publicFaq->id,
                            'location:' . $location->id,
                        ],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
        ], 200),
    ]);

    $response = $this->postJson('/api/public/leads', [
        'location_id' => $location->id,
        'message' => 'Do you offer winter storage?',
        'client_message_id' => 'public-visibility-1',
        'visitor_id' => 'visitor-public-visibility-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('ai_message.sender_type', 'ai');

    Http::assertSent(function ($request) use ($publicFaq) {
        if ($request->url() !== 'https://api.openai.com/v1/responses') {
            return false;
        }

        $payload = (string) data_get($request->data(), 'input.0.content.0.text');

        return str_contains($payload, $publicFaq->answer)
            && ! str_contains($payload, 'Internal policy: only quote winter storage after staff approval.')
            && str_contains($payload, '"visibility":"public"');
    });
});

test('public lead creation can infer location from the linked yacht when location_id is omitted', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.chat_ai.provider', 'openai');

    $location = Location::create([
        'name' => 'Muiden Marina',
        'code' => 'MUI',
        'status' => 'ACTIVE',
    ]);

    $yacht = Yacht::create([
        'location_id' => $location->id,
        'boat_name' => 'Grand Soleil 40',
        'manufacturer' => 'Grand Soleil',
        'model' => '40',
        'status' => 'available',
        'external_url' => 'https://schepen-kring.nl/yachts/grand-soleil-40',
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_chat_inferred_location',
            'model' => 'gpt-5-mini',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'reply' => 'I can help with questions about this yacht.',
                        'confidence' => 0.61,
                        'should_handoff' => false,
                        'handoff_reason' => '',
                        'used_sources' => [
                            'yacht:' . $yacht->id,
                            'location:' . $location->id,
                        ],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
        ], 200),
    ]);

    $response = $this->postJson('/api/public/leads', [
        'source_url' => $yacht->external_url,
        'message' => 'Can you tell me more about this yacht?',
        'client_message_id' => 'public-infer-location-1',
        'visitor_id' => 'visitor-infer-location-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('conversation.location_id', $location->id)
        ->assertJsonPath('conversation.boat_id', $yacht->id)
        ->assertJsonPath('lead.location_id', $location->id)
        ->assertJsonPath('lead.yacht_id', $yacht->id);
});

test('public lead creation can fall back to the default public chat location when no location context is provided', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.chat_ai.provider', 'openai');

    $location = Location::create([
        'name' => 'Harlingen Marina',
        'code' => 'HAR',
        'status' => 'ACTIVE',
        'chat_widget_enabled' => true,
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_chat_single_location_fallback',
            'model' => 'gpt-5-mini',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'reply' => 'I can help with questions about this location.',
                        'confidence' => 0.59,
                        'should_handoff' => false,
                        'handoff_reason' => '',
                        'used_sources' => [
                            'location:' . $location->id,
                        ],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
        ], 200),
    ]);

    $response = $this->postJson('/api/public/leads', [
        'message' => 'Do you offer mooring help?',
        'client_message_id' => 'public-single-location-fallback-1',
        'visitor_id' => 'visitor-single-location-fallback-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('conversation.location_id', $location->id)
        ->assertJsonPath('lead.location_id', $location->id);
});

test('public lead creation falls back to the first public chat location when multiple chat locations exist', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.chat_ai.provider', 'openai');

    $defaultLocation = Location::create([
        'name' => 'Alpha Marina',
        'code' => 'ALP',
        'status' => 'ACTIVE',
        'chat_widget_enabled' => true,
    ]);

    Location::create([
        'name' => 'Zulu Marina',
        'code' => 'ZUL',
        'status' => 'ACTIVE',
        'chat_widget_enabled' => true,
    ]);

    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_chat_default_location_fallback',
            'model' => 'gpt-5-mini',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'reply' => 'I can help with questions about this location.',
                        'confidence' => 0.58,
                        'should_handoff' => false,
                        'handoff_reason' => '',
                        'used_sources' => [
                            'location:' . $defaultLocation->id,
                        ],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
        ], 200),
    ]);

    $response = $this->postJson('/api/public/leads', [
        'message' => 'Can you help me?',
        'client_message_id' => 'public-ambiguous-location-1',
        'visitor_id' => 'visitor-ambiguous-location-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('conversation.location_id', $defaultLocation->id)
        ->assertJsonPath('lead.location_id', $defaultLocation->id);
});

test('public conversation message deduplicates and returns the existing ai reply', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.chat_ai.provider', 'openai');

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

test('public chat greets visitors naturally without escalating small talk', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.chat_ai.provider', 'openai');

    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    Http::fake();

    $response = $this->postJson('/api/public/leads', [
        'location_id' => $location->id,
        'message' => 'How are you?',
        'client_message_id' => 'public-greeting-1',
        'visitor_id' => 'visitor-greeting-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('ai_message.sender_type', 'ai')
        ->assertJsonPath('ai_message.metadata.provider', 'fallback')
        ->assertJsonPath('ai_message.metadata.should_handoff', false)
        ->assertJsonPath('conversation.status', 'open');

    expect((string) $response->json('ai_message.text'))
        ->toContain('Rotterdam Harbor')
        ->toContain('What would you like to know?');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.openai.com/v1/responses'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
});

test('public chat gives a guided no-match reply without auto handoff', function () {
    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $response = $this->postJson('/api/public/leads', [
        'location_id' => $location->id,
        'message' => 'Do you offer moon parking for yachts?',
        'client_message_id' => 'public-no-match-1',
        'visitor_id' => 'visitor-no-match-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('ai_message.sender_type', 'ai')
        ->assertJsonPath('ai_message.metadata.provider', 'fallback')
        ->assertJsonPath('ai_message.metadata.should_handoff', false)
        ->assertJsonPath('ai_message.metadata.handoff_reason', '')
        ->assertJsonPath('conversation.status', 'open');

    expect((string) $response->json('ai_message.text'))
        ->toContain('trusted website answer')
        ->toContain('available yachts');
});

test('generic public chat message endpoint also returns a stored ai reply for visitors', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.chat_ai.provider', 'openai');

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
