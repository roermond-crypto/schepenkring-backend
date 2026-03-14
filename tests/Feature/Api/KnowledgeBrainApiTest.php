<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Faq;
use App\Models\FaqKnowledgeDocument;
use App\Models\FaqKnowledgeItem;
use App\Models\KnowledgeBrainQuestion;
use App\Models\KnowledgeBrainSuggestion;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('copilot no-match queries are captured as knowledge brain missing questions', function () {
    $location = Location::create([
        'name' => 'Schepenkring HQ',
        'code' => 'SKHQ',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $this->postJson('/api/copilot/resolve', [
        'text' => 'Can I insure my boat while it is stored in a marina?',
        'source' => 'chatpage',
        'context' => [
            'location_id' => $location->id,
            'language' => 'en',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('answer_strategy', 'no_match');

    $question = KnowledgeBrainQuestion::query()->first();
    expect($question)->not->toBeNull();
    expect($question->location_id)->toBe($location->id);
    expect($question->question)->toBe('Can I insure my boat while it is stored in a marina?');
    expect($question->times_asked)->toBe(1);

    $suggestion = KnowledgeBrainSuggestion::query()->first();
    expect($suggestion)->not->toBeNull();
    expect($suggestion->type)->toBe('missing_question');
    expect($suggestion->question_id)->toBe($question->id);
    expect($suggestion->status)->toBe('pending');
});

test('admin knowledge brain dashboard returns overview metrics and training status', function () {
    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $indexedAt = now()->subMinutes(2);
    Faq::create([
        'location_id' => $location->id,
        'question' => 'How do I insure my boat?',
        'answer' => 'Boat insurance covers agreed damage and liability risks.',
        'category' => 'Insurance',
        'source_type' => 'faq',
        'last_indexed_at' => $indexedAt,
        'created_at' => $indexedAt,
        'updated_at' => $indexedAt,
    ]);

    Faq::create([
        'location_id' => $location->id,
        'question' => 'Can I store my boat in winter?',
        'answer' => 'Yes.',
        'category' => 'Storage',
        'source_type' => 'faq',
    ]);

    $question = KnowledgeBrainQuestion::create([
        'location_id' => $location->id,
        'question' => 'Can I insure my boat in winter storage?',
        'normalized_question' => 'can i insure my boat in winter storage',
        'source_type' => 'copilot',
        'status' => 'pending',
        'times_asked' => 3,
        'confidence' => 0.41,
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now(),
    ]);

    KnowledgeBrainSuggestion::create([
        'location_id' => $location->id,
        'question_id' => $question->id,
        'fingerprint' => 'missing-question-dashboard-1',
        'type' => 'missing_question',
        'status' => 'pending',
        'title' => 'Suggested new FAQ',
        'source_type' => 'copilot',
        'question' => $question->question,
        'summary' => 'AI could not answer this confidently.',
        'first_detected_at' => now()->subDay(),
        'last_detected_at' => now(),
    ]);

    $document = FaqKnowledgeDocument::create([
        'location_id' => $location->id,
        'uploaded_by_user_id' => $admin->id,
        'file_name' => 'winter-storage-guide.pdf',
        'file_path' => 'faq-knowledge/winter-storage-guide.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'source_type' => 'pdf_document',
        'status' => 'pending_review',
        'generated_qna_count' => 2,
        'chunk_count' => 1,
    ]);

    FaqKnowledgeItem::create([
        'document_id' => $document->id,
        'location_id' => $location->id,
        'status' => 'pending',
        'source_type' => 'pdf_document',
        'question' => 'Does winter storage affect boat insurance?',
        'answer' => 'Insurance validity depends on storage conditions and policy terms.',
    ]);

    Sanctum::actingAs($admin);

    $this->getJson("/api/faqs/knowledge-brain?location_id={$location->id}")
        ->assertOk()
        ->assertJsonPath('overview.documents_analyzed', 1)
        ->assertJsonPath('overview.total_qna', 2)
        ->assertJsonPath('overview.missing_questions', 1)
        ->assertJsonPath('training_status.vectors_stored', 1)
        ->assertJsonPath('training_status.pending_embeddings', 1)
        ->assertJsonPath('missing_questions.0.id', $question->id);
});

test('refreshing the knowledge brain creates improvement duplicate and document suggestions', function () {
    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $faqA = Faq::create([
        'location_id' => $location->id,
        'question' => 'How do I insure my boat?',
        'answer' => 'Boat insurance covers damage.',
        'category' => 'Insurance',
        'source_type' => 'faq',
    ]);

    $faqB = Faq::create([
        'location_id' => $location->id,
        'question' => 'How can I get boat insurance?',
        'answer' => 'You can request a quote from our insurance team.',
        'category' => 'Insurance',
        'source_type' => 'faq',
    ]);

    $document = FaqKnowledgeDocument::create([
        'location_id' => $location->id,
        'uploaded_by_user_id' => $admin->id,
        'file_name' => 'marina-safety-rules.pdf',
        'file_path' => 'faq-knowledge/marina-safety-rules.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'source_type' => 'pdf_document',
        'status' => 'pending_review',
        'generated_qna_count' => 2,
        'chunk_count' => 1,
    ]);

    FaqKnowledgeItem::create([
        'document_id' => $document->id,
        'location_id' => $location->id,
        'status' => 'pending',
        'source_type' => 'pdf_document',
        'question' => 'Are fire extinguishers mandatory on boats?',
        'answer' => 'Yes, safety requirements depend on marina policy.',
    ]);

    Sanctum::actingAs($admin);

    $this->postJson('/api/faqs/knowledge-brain/refresh', [
        'location_id' => $location->id,
    ])
        ->assertOk()
        ->assertJsonPath('summary.faq_reviews', 2)
        ->assertJsonPath('summary.duplicate_suggestions', 1)
        ->assertJsonPath('summary.document_suggestions', 1);

    expect($faqA->fresh()->needs_update)->toBeTrue();
    expect($faqA->fresh()->ai_score)->not->toBeNull();

    $types = KnowledgeBrainSuggestion::query()
        ->where('location_id', $location->id)
        ->pluck('type')
        ->all();

    expect($types)->toContain('answer_improvement');
    expect($types)->toContain('duplicate_cluster');
    expect($types)->toContain('document_gap');
    expect($faqB->id)->not->toBeNull(); // keep both FAQs referenced in test
});

test('admin can approve a missing-question suggestion into a faq', function () {
    $location = Location::create([
        'name' => 'Lelystad Harbor',
        'code' => 'LLS',
        'status' => 'ACTIVE',
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $question = KnowledgeBrainQuestion::create([
        'location_id' => $location->id,
        'question' => 'Can I insure my boat while it is stored in a marina?',
        'normalized_question' => 'can i insure my boat while it is stored in a marina',
        'source_type' => 'copilot',
        'status' => 'pending',
        'times_asked' => 2,
        'confidence' => 0.42,
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now(),
    ]);

    $suggestion = KnowledgeBrainSuggestion::create([
        'location_id' => $location->id,
        'question_id' => $question->id,
        'fingerprint' => 'approve-missing-question-1',
        'type' => 'missing_question',
        'status' => 'pending',
        'title' => 'Suggested new FAQ',
        'source_type' => 'copilot',
        'question' => $question->question,
        'suggested_answer' => 'Boat insurance can remain valid while the vessel is stored in a marina as long as the policy conditions and marina safety requirements are met.',
        'summary' => 'AI could not answer this confidently from the current knowledge base.',
        'first_detected_at' => now()->subDay(),
        'last_detected_at' => now(),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->patchJson("/api/faqs/knowledge-brain/suggestions/{$suggestion->id}", [
        'status' => 'approved',
        'answer' => 'Boat insurance can remain valid while the vessel is stored in a marina as long as the policy conditions and marina safety requirements are met.',
        'category' => 'Insurance',
    ]);

    $response->assertOk()
        ->assertJsonPath('suggestion.status', 'approved')
        ->assertJsonPath('suggestion.question_id', $question->id);

    $faq = Faq::query()->first();
    expect($faq)->not->toBeNull();
    expect($faq->location_id)->toBe($location->id);
    expect($faq->question)->toBe($question->question);
    expect($faq->source_type)->toBe('knowledge_brain');

    expect($suggestion->fresh()->approved_faq_id)->toBe($faq->id);
    expect($question->fresh()->status)->toBe('approved');
    expect($question->fresh()->matched_faq_id)->toBe($faq->id);
});
