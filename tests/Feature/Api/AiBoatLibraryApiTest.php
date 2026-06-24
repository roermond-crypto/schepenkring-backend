<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Models\Yacht;
use App\Models\YachtDraft;
use App\Services\PineconeMatcherService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

test('ai library reindex is blocked until a scrape run passes completeness gate', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    ScrapeRun::create([
        'source' => 'schepenkring_sold_archive',
        'status' => 'below_threshold',
        'started_at' => now(),
        'finished_at' => now(),
        'expected_total' => 3100,
        'completeness_ratio' => 0.52,
    ]);

    Sanctum::actingAs($admin);

    $this->postJson('/api/admin/ai-library/reindex')
        ->assertStatus(409)
        ->assertJsonPath('message', 'Latest scrape run has not passed the completeness gate.');
});

test('ai library can delete stale vectors and reindex all verified archive yachts', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    ScrapeRun::create([
        'source' => 'schepenkring_sold_archive',
        'status' => 'completed',
        'started_at' => now(),
        'finished_at' => now(),
        'expected_total' => 3100,
        'completeness_ratio' => 0.99,
    ]);

    Yacht::create([
        'vessel_id' => 'archive-1',
        'boat_name' => 'Bayliner 2855',
        'manufacturer' => 'Bayliner',
        'model' => '2855',
        'status' => 'sold',
        'source' => 'schepenkring_sold_archive',
    ]);
    Yacht::create([
        'vessel_id' => 'archive-2',
        'boat_name' => 'Valk Super Falcon',
        'manufacturer' => 'Valk',
        'model' => 'Super Falcon 45',
        'status' => 'sold',
        'source' => 'schepenkring_sold_archive',
    ]);

    $fake = new class extends PineconeMatcherService {
        public array $upserts = [];
        public bool $deleted = false;

        public function upsertYacht(Yacht $yacht): bool
        {
            $this->upserts[] = $yacht->id;

            return true;
        }

        public function deleteAllYachtVectors(): bool
        {
            $this->deleted = true;

            return true;
        }
    };
    app()->instance(PineconeMatcherService::class, $fake);

    Sanctum::actingAs($admin);

    $this->postJson('/api/admin/ai-library/reindex?delete_existing=1')
        ->assertOk()
        ->assertJsonPath('indexed', 2)
        ->assertJsonPath('deleted_existing', true);

    expect($fake->deleted)->toBeTrue()
        ->and($fake->upserts)->toHaveCount(2);
});

test('sold boat index command blocks full indexing when latest scrape is incomplete', function () {
    ScrapeRun::create([
        'source' => 'schepenkring_sold_archive',
        'status' => 'below_threshold',
        'started_at' => now(),
        'finished_at' => now(),
        'expected_total' => 3100,
        'completeness_ratio' => 0.50,
    ]);

    Artisan::call('app:index-sold-boats');

    expect(Artisan::output())->toContain('Latest Schepenkring scrape did not pass the completeness gate');
});

test('draft ai autofill stores reviewable suggestions before applying approved fields', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'loa' => 8.65,
                        'beam' => 2.9,
                        'fuel' => 'petrol',
                    ]),
                ],
            ]],
        ]),
    ]);

    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);
    $client = User::factory()->create();
    $reference = Yacht::create([
        'vessel_id' => 'reference-1',
        'boat_name' => 'Bayliner 2855',
        'manufacturer' => 'Bayliner',
        'model' => '2855',
        'year' => 1997,
        'status' => 'sold',
        'source' => 'schepenkring_sold_archive',
        'external_url' => 'https://www.schepenkring.nl/verkochte-boten/123/bayliner-2855/',
    ]);
    $draft = YachtDraft::create([
        'user_id' => $client->id,
        'draft_id' => 'draft-123',
        'payload_json' => ['manufacturer' => 'Bayliner'],
        'ai_state_json' => ['reference_yacht_id' => $reference->id],
    ]);

    Sanctum::actingAs($admin);

    $this->postJson("/api/admin/yachts/draft/{$draft->draft_id}/ai-autofill")
        ->assertOk()
        ->assertJsonPath('review_required', true)
        ->assertJsonPath('source_log.source', 'verified_schepenkring_ai_library')
        ->assertJsonPath('suggested_fields.0', 'loa');

    $draft->refresh();
    expect($draft->payload_json)->toBe(['manufacturer' => 'Bayliner'])
        ->and($draft->ai_state_json['autofill_suggestions']['status'])->toBe('pending_review');

    $this->postJson("/api/admin/yachts/draft/{$draft->draft_id}/ai-autofill/apply", [
        'fields' => ['loa', 'beam'],
    ])
        ->assertOk()
        ->assertJsonPath('applied_fields.0', 'loa')
        ->assertJsonPath('applied_fields.1', 'beam');

    $draft->refresh();
    expect($draft->payload_json['loa'])->toBe(8.65)
        ->and($draft->payload_json['beam'])->toBe(2.9)
        ->and($draft->payload_json)->not->toHaveKey('fuel')
        ->and($draft->ai_state_json['autofill_suggestions']['status'])->toBe('approved');
});
