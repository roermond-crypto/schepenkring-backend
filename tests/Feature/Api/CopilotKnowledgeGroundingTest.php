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

test('copilot answers from the faq knowledge base first and logs the source trace', function () {
    $location = Location::create([
        'name' => 'Schepenkring HQ',
        'code' => 'SKHQ',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $nauticFaq = Faq::create([
        'location_id' => $location->id,
        'question' => 'How does Nautic Secure payment work?',
        'answer' => 'Nautic Secure payment is handled through our verified payment workflow with internal approval checks before release.',
        'category' => 'Payments',
        'language' => 'en',
        'brand' => 'Nautic Secure',
        'department' => 'Finance',
        'visibility' => 'internal',
        'source_type' => 'faq',
        'tags' => ['payment', 'escrow'],
    ]);

    $otherBrandFaq = Faq::create([
        'location_id' => $location->id,
        'question' => 'How does Nautic Secure payment work?',
        'answer' => 'This is an unrelated payment explanation for a different brand.',
        'category' => 'Payments',
        'language' => 'en',
        'brand' => 'Other Brand',
        'department' => 'Finance',
        'visibility' => 'internal',
        'source_type' => 'faq',
        'tags' => ['payment'],
    ]);

    config()->set('services.openai.key', 'test-openai');
    config()->set('services.pinecone.key', 'test-pinecone');
    config()->set('services.pinecone.host', 'https://pinecone.test');
    config()->set('services.pinecone.namespace', 'copilot');

    Http::fake([
        'https://api.openai.com/v1/embeddings' => Http::response([
            'data' => [[
                'embedding' => [0.11, 0.22, 0.33],
            ]],
        ], 200),
        'https://pinecone.test/query' => Http::response([
            'matches' => [
                [
                    'id' => 'faq-' . $otherBrandFaq->id,
                    'score' => 0.97,
                    'metadata' => [
                        'faq_id' => $otherBrandFaq->id,
                        'question' => $otherBrandFaq->question,
                        'answer' => $otherBrandFaq->answer,
                        'category' => $otherBrandFaq->category,
                        'location_id' => $location->id,
                    ],
                ],
                [
                    'id' => 'faq-' . $nauticFaq->id,
                    'score' => 0.94,
                    'metadata' => [
                        'faq_id' => $nauticFaq->id,
                        'question' => $nauticFaq->question,
                        'answer' => $nauticFaq->answer,
                        'category' => $nauticFaq->category,
                        'location_id' => $location->id,
                    ],
                ],
            ],
        ], 200),
        'https://pinecone.test/vectors/upsert' => Http::response([
            'upsertedCount' => 1,
        ], 200),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/copilot/resolve', [
        'text' => 'How does Nautic Secure payment work?',
        'source' => 'chatpage',
        'context' => [
            'location_id' => $location->id,
            'brand' => 'Nautic Secure',
            'department' => 'Finance',
            'language' => 'en',
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('answers.0.answer', $nauticFaq->answer)
        ->assertJsonPath('answers.0.source', 'faq')
        ->assertJsonPath('answers.0.strategy', 'faq_primary')
        ->assertJsonPath('answers.0.confidence_label', 'high')
        ->assertJsonPath('answers.0.sources.0.faq_id', $nauticFaq->id)
        ->assertJsonPath('answers.0.sources.0.brand', 'Nautic Secure')
        ->assertJsonPath('knowledge_trace.strategy', 'faq_primary')
        ->assertJsonPath('knowledge_trace.used_source_ids.0', $nauticFaq->id)
        ->assertJsonPath('knowledge_trace.filters.brand', 'Nautic Secure');

    $audit = CopilotAuditEvent::query()->latest('id')->first();

    expect($audit)->not->toBeNull();
    expect($audit->status)->toBe('resolved');
    expect(data_get($audit->matching_detail, 'answers.0.sources.0.faq_id'))->toBe($nauticFaq->id);
    expect(data_get($audit->matching_detail, 'knowledge_trace.used_source_ids.0'))->toBe($nauticFaq->id);

    Http::assertSent(function ($request) use ($location) {
        if ($request->url() !== 'https://pinecone.test/query') {
            return false;
        }

        return data_get($request->data(), 'filter.location_id.$eq') === $location->id
            && data_get($request->data(), 'filter.kind.$eq') === 'faq';
    });
});
