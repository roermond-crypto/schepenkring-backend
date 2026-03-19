<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Faq;
use App\Models\FaqKnowledgeDocument;
use App\Models\FaqKnowledgeItem;
use App\Models\KnowledgeEntity;
use App\Models\KnowledgeIngestionRun;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('staff can upload a knowledge document and generate pending faq review items', function () {
    Storage::fake('local');

    $location = Location::create([
        'name' => 'Schepenkring HQ',
        'code' => 'SKHQ',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    config()->set('services.openai.key', 'test-openai');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'items' => [
                            [
                                'question' => 'What documents are needed to sell a boat?',
                                'answer' => 'The sales package requires the registration papers, proof of identity, and the signed sale agreement.',
                            ],
                            [
                                'question' => 'Do sellers need proof of identity?',
                                'answer' => 'Yes. A valid proof of identity is required before the sale file can be completed.',
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ]],
        ], 200),
    ]);

    Sanctum::actingAs($employee);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/faqs/knowledge/documents', [
            'location_id' => $location->id,
            'category' => 'Sales',
            'source_type' => 'pdf_document',
            'file' => UploadedFile::fake()->createWithContent(
                'knowledge.txt',
                "Selling a boat requires registration papers, proof of identity, and a signed sale agreement."
            ),
        ]);

    $response->assertCreated()
        ->assertJsonPath('document.status', 'pending_review')
        ->assertJsonPath('document.generated_qna_count', 2)
        ->assertJsonPath('items.0.status', 'pending')
        ->assertJsonPath('items.0.category', 'Sales');

    $document = FaqKnowledgeDocument::query()->first();
    $item = FaqKnowledgeItem::query()->first();
    $documentEntity = KnowledgeEntity::query()
        ->where('type', 'document')
        ->where('source_table', 'faq_knowledge_documents')
        ->where('source_id', $document?->id)
        ->first();
    $run = KnowledgeIngestionRun::query()->first();

    expect($document)->not->toBeNull();
    expect($document->source_type)->toBe('pdf_document');
    expect($item)->not->toBeNull();
    expect($item->status)->toBe('pending');
    expect($item->approved_faq_id)->toBeNull();
    expect($documentEntity)->not->toBeNull();
    expect($documentEntity->title)->toBe($document->file_name);
    expect($run)->not->toBeNull();
    expect($run->status)->toBe('completed');
    expect($run->documents_count)->toBe(1);
    expect($run->chunks_count)->toBe(1);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/vectors/upsert'));
});

test('approving a generated knowledge item creates a faq and upserts it to pinecone', function () {
    Storage::fake('local');

    $location = Location::create([
        'name' => 'Schepenkring HQ',
        'code' => 'SKHQ',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);
    $employee->locations()->attach($location->id, ['role' => 'sales']);

    config()->set('services.openai.key', 'test-openai');
    config()->set('services.pinecone.key', 'test-pinecone');
    config()->set('services.pinecone.host', 'https://pinecone.test');
    config()->set('services.pinecone.namespace', 'copilot');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'items' => [[
                            'question' => 'Can a client change booking after payment?',
                            'answer' => 'Clients can request a booking change after payment, but approval depends on timing and the configured policy.',
                        ]],
                    ], JSON_UNESCAPED_SLASHES),
                ],
            ]],
        ], 200),
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

    $upload = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/faqs/knowledge/documents', [
            'location_id' => $location->id,
            'category' => 'Bookings',
            'department' => 'Sales',
            'visibility' => 'internal',
            'file' => UploadedFile::fake()->createWithContent(
                'booking-policy.txt',
                "Clients can request a booking change after payment. Approval depends on timing and company policy."
            ),
        ]);

    $upload->assertCreated();

    $item = FaqKnowledgeItem::query()->first();
    expect($item)->not->toBeNull();

    $review = $this->patchJson("/api/faqs/knowledge/items/{$item->id}", [
        'status' => 'approved',
        'question' => 'Can a client change booking after payment?',
        'answer' => 'Clients can request a booking change after payment, but approval depends on timing and the configured company policy.',
    ]);

    $review->assertOk()
        ->assertJsonPath('item.status', 'approved')
        ->assertJsonPath('item.approved_faq_id', 1);

    $faq = Faq::query()->first();
    $faqEntity = KnowledgeEntity::query()
        ->where('type', 'faq')
        ->where('source_table', 'faqs')
        ->where('source_id', $faq?->id)
        ->first();

    expect($faq)->not->toBeNull();
    expect($faq->question)->toBe('Can a client change booking after payment?');
    expect($faq->source_type)->toBe('text_document');
    expect($item->fresh()->approved_faq_id)->toBe($faq->id);
    expect($faqEntity)->not->toBeNull();
    expect(data_get($faqEntity->metadata, 'source_type'))->toBe('text_document');

    Http::assertSent(fn ($request) => $request->url() === 'https://pinecone.test/vectors/upsert');
});
