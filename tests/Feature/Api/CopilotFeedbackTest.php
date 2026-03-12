<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\CopilotAuditEvent;
use App\Models\Faq;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('copilot feedback stores a corrected faq and deprecates the bad answer', function () {
    $location = Location::create([
        'name' => 'Marina One',
        'code' => 'M1',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    $wrongFaq = Faq::create([
        'location_id' => $location->id,
        'question' => 'Can a client change booking after payment?',
        'answer' => 'No, changes are never allowed after payment.',
        'category' => 'Bookings',
        'language' => 'en',
        'visibility' => 'internal',
        'source_type' => 'faq',
    ]);

    $auditEvent = CopilotAuditEvent::create([
        'user_id' => $employee->id,
        'source' => 'chatpage',
        'input_text' => 'Can a client change booking after payment?',
        'matching_detail' => [
            'answers' => [[
                'answer' => $wrongFaq->answer,
                'sources' => [[
                    'faq_id' => $wrongFaq->id,
                ]],
            ]],
            'knowledge_trace' => [
                'used_source_ids' => [$wrongFaq->id],
            ],
        ],
        'status' => 'resolved',
        'created_at' => now(),
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
        'https://pinecone.test/vectors/delete' => Http::response([
            'status' => 'ok',
        ], 200),
    ]);

    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/copilot/feedback', [
        'audit_event_id' => $auditEvent->id,
        'corrected_answer' => 'Clients can request a booking change after payment, but approval depends on timing and the configured company policy.',
        'category' => 'Bookings',
        'tags' => ['booking', 'payment'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Feedback stored')
        ->assertJsonPath('faq.question', 'Can a client change booking after payment?')
        ->assertJsonPath('faq.answer', 'Clients can request a booking change after payment, but approval depends on timing and the configured company policy.')
        ->assertJsonPath('superseded_faq_id', $wrongFaq->id);

    $newFaqId = (int) $response->json('faq.id');

    expect($newFaqId)->not->toBe($wrongFaq->id);
    expect(Faq::query()->findOrFail($newFaqId)->tags)->toBe(['booking', 'payment']);
    expect($wrongFaq->fresh()->deprecated_at)->not->toBeNull();
    expect($wrongFaq->fresh()->superseded_by_faq_id)->toBe($newFaqId);

    $feedbackEvent = CopilotAuditEvent::query()
        ->where('source', 'learning')
        ->where('stage', 'feedback')
        ->latest('id')
        ->first();

    expect($feedbackEvent)->not->toBeNull();
    expect($feedbackEvent->status)->toBe('corrected');
    expect(data_get($feedbackEvent->matching_detail, 'audit_event_id'))->toBe($auditEvent->id);
    expect(data_get($feedbackEvent->matching_detail, 'superseded_faq_id'))->toBe($wrongFaq->id);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/vectors/upsert'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/vectors/delete'));
});
