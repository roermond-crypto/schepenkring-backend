<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('copilot resolves dutch input and updates the user locale', function () {
    $user = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
        'locale' => 'en',
    ]);

    Sanctum::actingAs($user);

    $response = $this
        ->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])
        ->postJson('/api/copilot/resolve', [
            'text' => 'Hoe open ik een klant?',
        ]);

    $response->assertOk()
        ->assertHeader('Content-Language', 'nl')
        ->assertHeader('X-Header-Language', 'NL')
        ->assertJsonPath('language', 'nl')
        ->assertJsonPath('header_language', 'NL')
        ->assertJsonPath('language_detected_from_input', true)
        ->assertJsonPath('locale_updated', true)
        ->assertJsonPath('clarifying_question', 'Kunt u specificeren wat u wilt openen of zoeken?');

    expect($user->fresh()->locale)->toBe('nl');
});

test('chat messages sync dutch language to the conversation and authenticated user', function () {
    $user = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
        'locale' => 'en',
    ]);

    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $contact = Contact::create([
        'name' => 'Visitor',
        'email' => 'visitor@example.com',
        'language_preferred' => 'en',
        'consent_service_messages' => true,
    ]);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'contact_id' => $contact->id,
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
        'language_preferred' => 'en',
    ]);

    Sanctum::actingAs($user);

    $response = $this
        ->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])
        ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
            'text' => 'Ik wil een klant openen',
        ]);

    $response->assertCreated()
        ->assertHeader('Content-Language', 'nl')
        ->assertHeader('X-Header-Language', 'NL')
        ->assertJsonPath('language', 'nl')
        ->assertJsonPath('header_language', 'NL')
        ->assertJsonPath('language_detected_from_input', true)
        ->assertJsonPath('sender_type', 'admin');

    expect($conversation->fresh()->language_preferred)->toBe('nl');
    expect($conversation->fresh()->language_detected)->toBe('nl');
    expect($user->fresh()->locale)->toBe('nl');
});

test('public conversation messages sync dutch language to the conversation and contact', function () {
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
        'channel' => 'web_widget',
        'channel_origin' => 'web_widget',
        'status' => 'open',
        'language_preferred' => 'en',
    ]);

    $response = $this
        ->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])
        ->postJson("/api/public/conversations/{$conversation->id}/messages", [
            'body' => 'Hoe werkt dit?',
            'client_message_id' => 'public-msg-1',
        ]);

    $response->assertCreated()
        ->assertHeader('Content-Language', 'nl')
        ->assertHeader('X-Header-Language', 'NL')
        ->assertJsonPath('message.language', 'nl')
        ->assertJsonPath('language', 'nl')
        ->assertJsonPath('header_language', 'NL')
        ->assertJsonPath('language_detected_from_input', true);

    expect($conversation->fresh()->language_preferred)->toBe('nl');
    expect($conversation->fresh()->language_detected)->toBe('nl');
    expect($contact->fresh()->language_preferred)->toBe('nl');
});
