<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\KnowledgeEntity;
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
    $entity = KnowledgeEntity::query()
        ->where('type', 'faq')
        ->where('source_table', 'faqs')
        ->where('source_id', $faq?->id)
        ->first();

    expect($faq)->not->toBeNull();
    expect($faq->trained_by_user_id)->toBe($employee->id);
    expect($answer->fresh()->metadata['faq_id'] ?? null)->toBe($faq->id);
    expect($entity)->not->toBeNull();
    expect($entity->title)->toBe('How late are you open?');
    expect(data_get($entity->metadata, 'visibility'))->toBe('internal');

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

test('staff can bulk update faq visibility for an accessible location without clearing training ownership', function () {
    $location = Location::create(['name' => 'Marina One', 'code' => 'M1']);

    $trainer = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    $faqA = Faq::create([
        'location_id' => $location->id,
        'question' => 'Do you have fuel?',
        'answer' => 'Yes, diesel is available at the dock.',
        'category' => 'Services',
        'visibility' => 'internal',
        'trained_by_user_id' => $trainer->id,
    ]);

    $faqB = Faq::create([
        'location_id' => $location->id,
        'question' => 'Can I book a berth online?',
        'answer' => 'Yes, bookings are available through the website.',
        'category' => 'Bookings',
        'visibility' => 'staff',
        'trained_by_user_id' => $trainer->id,
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

    $response = $this->postJson('/api/faqs/bulk', [
        'action' => 'update_visibility',
        'location_id' => $location->id,
        'faq_ids' => [$faqA->id, $faqB->id],
        'visibility' => 'public',
    ]);

    $response->assertOk()
        ->assertJsonPath('action', 'update_visibility')
        ->assertJsonPath('count', 2)
        ->assertJsonPath('visibility', 'public');

    expect($faqA->fresh()->visibility)->toBe('public');
    expect($faqB->fresh()->visibility)->toBe('public');
    expect($faqA->fresh()->trained_by_user_id)->toBe($trainer->id);
    expect($faqB->fresh()->trained_by_user_id)->toBe($trainer->id);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/vectors/upsert'));
});

test('staff cannot bulk update faq visibility outside their accessible locations', function () {
    $locationA = Location::create(['name' => 'Marina One', 'code' => 'M1']);
    $locationB = Location::create(['name' => 'Marina Two', 'code' => 'M2']);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($locationA->id, ['role' => 'sales']);

    $faq = Faq::create([
        'location_id' => $locationB->id,
        'question' => 'Is guest parking available?',
        'answer' => 'Yes, there is guest parking at the front gate.',
        'category' => 'Access',
    ]);

    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/faqs/bulk', [
        'action' => 'update_visibility',
        'faq_ids' => [$faq->id],
        'visibility' => 'public',
    ]);

    $response->assertForbidden();
    expect($faq->fresh()->visibility)->not->toBe('public');
});

test('staff can bulk delete faqs for an accessible location', function () {
    $location = Location::create(['name' => 'Marina One', 'code' => 'M1']);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    $faqA = Faq::create([
        'location_id' => $location->id,
        'question' => 'Do you sell ice?',
        'answer' => 'Yes, ice is available from reception.',
        'category' => 'Shop',
    ]);

    $faqB = Faq::create([
        'location_id' => $location->id,
        'question' => 'Do you have showers?',
        'answer' => 'Yes, showers are open 24/7.',
        'category' => 'Facilities',
    ]);

    config()->set('services.pinecone.key', 'test-pinecone');
    config()->set('services.pinecone.host', 'https://pinecone.test');
    config()->set('services.pinecone.namespace', 'copilot');

    Http::fake([
        'https://pinecone.test/vectors/delete' => Http::response([
            'status' => 'ok',
        ], 200),
    ]);

    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/faqs/bulk', [
        'action' => 'delete',
        'location_id' => $location->id,
        'faq_ids' => [$faqA->id, $faqB->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('action', 'delete')
        ->assertJsonPath('count', 2);

    expect(Faq::query()->whereKey($faqA->id)->exists())->toBeFalse();
    expect(Faq::query()->whereKey($faqB->id)->exists())->toBeFalse();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/vectors/delete'));
});
