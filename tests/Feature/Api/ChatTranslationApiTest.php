<?php

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTranslationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_chat_translation_returns_translated_text(): void
    {
        config()->set('services.openai.key', 'test-openai-key');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"translated_text":"I want details about this boat","source_language":"nl"}',
                    ],
                ]],
            ]),
        ]);

        $user = User::factory()->create([
            'type' => UserType::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $location = Location::create([
            'name' => 'Amsterdam Marina',
            'code' => 'AMS',
            'status' => 'ACTIVE',
        ]);

        $contact = Contact::create([
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'language_preferred' => 'nl',
            'consent_service_messages' => true,
        ]);

        $conversation = Conversation::create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'channel' => 'web_widget',
            'channel_origin' => 'web_widget',
            'status' => 'open',
            'language_preferred' => 'nl',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/chat/translate', [
            'conversation_id' => $conversation->id,
            'text' => 'Ik wil details over deze boot',
            'target_language' => 'english',
        ])
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertHeader('X-Source-Language', 'nl')
            ->assertJsonPath('conversation_id', $conversation->id)
            ->assertJsonPath('original_text', 'Ik wil details over deze boot')
            ->assertJsonPath('translated_text', 'I want details about this boat')
            ->assertJsonPath('source_language', 'nl')
            ->assertJsonPath('target_language', 'en')
            ->assertJsonPath('provider', 'openai');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['model'] === 'gpt-4o-mini';
        });
    }

    public function test_public_chat_translation_supports_widget_session_tokens(): void
    {
        config()->set('services.openai.key', 'test-openai-key');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"translated_text":"Je voudrais des details sur ce bateau","source_language":"en"}',
                    ],
                ]],
            ]),
        ]);

        $location = Location::create([
            'name' => 'Rotterdam Harbor',
            'code' => 'RTM',
            'status' => 'ACTIVE',
        ]);

        $contact = Contact::create([
            'name' => 'Public Visitor',
            'email' => 'public@example.com',
            'language_preferred' => 'en',
            'consent_service_messages' => true,
        ]);

        $conversation = Conversation::create([
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'visitor_id' => 'visitor-123',
            'channel' => 'web_widget',
            'channel_origin' => 'web_widget',
            'status' => 'open',
            'language_preferred' => 'en',
        ]);

        $sessionJwt = Crypt::encryptString(json_encode([
            'visitor_id' => 'visitor-123',
            'session_id' => 'session-1',
            'issued_at' => now()->timestamp,
        ]));

        $this->postJson('/api/public/chat/translate', [
            'conversation_id' => $conversation->id,
            'text' => 'I want details about this boat',
            'target_language' => 'fr',
            'session_jwt' => $sessionJwt,
        ])
            ->assertOk()
            ->assertHeader('Content-Language', 'fr')
            ->assertHeader('X-Source-Language', 'en')
            ->assertJsonPath('translated_text', 'Je voudrais des details sur ce bateau')
            ->assertJsonPath('target_language', 'fr');
    }

    public function test_public_chat_translation_rejects_mismatched_widget_visitor(): void
    {
        config()->set('services.openai.key', 'test-openai-key');

        Http::fake();

        $location = Location::create([
            'name' => 'Den Helder',
            'code' => 'DHD',
            'status' => 'ACTIVE',
        ]);

        $conversation = Conversation::create([
            'location_id' => $location->id,
            'visitor_id' => 'visitor-allowed',
            'channel' => 'web_widget',
            'channel_origin' => 'web_widget',
            'status' => 'open',
            'language_preferred' => 'en',
        ]);

        $sessionJwt = Crypt::encryptString(json_encode([
            'visitor_id' => 'visitor-denied',
            'session_id' => 'session-2',
            'issued_at' => now()->timestamp,
        ]));

        $this->postJson('/api/public/chat/translate', [
            'conversation_id' => $conversation->id,
            'text' => 'I want details',
            'target_language' => 'de',
            'session_jwt' => $sessionJwt,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');

        Http::assertNothingSent();
    }
}
