<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('staff thumbs up trains a location faq and upserts it to pinecone', function () {
    $location = Location::create([
        'name' => 'Marina One',
        'code' => 'M1',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    $conversation = Conversation::create([
        'location_id' => $location->id,
        'channel' => 'web_widget',
        'status' => 'open',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'visitor',
        'text' => 'How late are you open?',
        'body' => 'How late are you open?',
    ]);

    $answer = Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'admin',
        'employee_id' => $employee->id,
        'text' => 'We are open until 8 PM every day.',
        'body' => 'We are open until 8 PM every day.',
    ]);

    config()->set('services.openai.key', 'test-openai');
    config()->set('services.pinecone.key', 'test-pinecone');
    config()->set('services.pinecone.host', 'https://pinecone.test');
    config()->set('services.pinecone.namespace', 'copilot');

    Http::fake([
        'https://api.openai.com/v1/embeddings' => Http::response([
            'data' => [[
                'embedding' => [0.1, 0.2, 0.3],
            ]],
        ], 200),
        'https://pinecone.test/vectors/upsert' => Http::response([
            'upsertedCount' => 1,
        ], 200),
    ]);

    Sanctum::actingAs($employee);

    $response = $this->postJson("/api/chat/messages/{$answer->id}/thumbs-up");

    $response->assertOk()
        ->assertJsonPath('message', 'FAQ trained')
        ->assertJsonPath('faq.location_id', $location->id)
        ->assertJsonPath('faq.question', 'How late are you open?')
        ->assertJsonPath('faq.answer', 'We are open until 8 PM every day.');

    $faq = Faq::query()->where('location_id', $location->id)->where('question', 'How late are you open?')->first();

    expect($faq)->not->toBeNull();
    expect($faq->trained_by_user_id)->toBe($employee->id);
    expect($answer->fresh()->metadata['faq_id'] ?? null)->toBe($faq->id);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/vectors/upsert'));
});

test('faq listing is scoped to the staff users location access', function () {
    $locationA = Location::create(['name' => 'Marina One', 'code' => 'M1']);
    $locationB = Location::create(['name' => 'Marina Two', 'code' => 'M2']);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($locationA->id, ['role' => 'sales']);

    Faq::create([
        'location_id' => $locationA->id,
        'question' => 'Question A',
        'answer' => 'Answer A',
        'category' => 'Chat',
    ]);

    Faq::create([
        'location_id' => $locationB->id,
        'question' => 'Question B',
        'answer' => 'Answer B',
        'category' => 'Chat',
    ]);

    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/faqs');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.question', 'Question A');
});

test('copilot faq answers are filtered by location scope', function () {
    $locationA = Location::create(['name' => 'Marina One', 'code' => 'M1']);
    $locationB = Location::create(['name' => 'Marina Two', 'code' => 'M2']);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Faq::create([
        'location_id' => $locationA->id,
        'question' => 'Where are you located?',
        'answer' => 'We are in Marina One.',
        'category' => 'General',
    ]);

    Faq::create([
        'location_id' => $locationB->id,
        'question' => 'Where are you located?',
        'answer' => 'We are in Marina Two.',
        'category' => 'General',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/copilot/resolve', [
        'text' => 'Where are you located?',
        'source' => 'header',
        'context' => [
            'location_id' => $locationB->id,
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('answers.0.answer', 'We are in Marina Two.');
});
