<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\BoatField;
use App\Models\BoatFieldMapping;
use App\Models\BoatFieldPriority;
use App\Models\BoatFieldValueObservation;
use App\Models\User;
use Database\Seeders\BoatFieldMappingSeeder;
use Database\Seeders\BoatFieldSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BoatFieldConfigApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_boat_form_config(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $cabins = BoatField::create([
            'internal_key' => 'cabins',
            'labels_json' => ['en' => 'Cabins', 'nl' => 'Hutten'],
            'field_type' => 'number',
            'block_key' => 'interior',
            'step_key' => 'specs',
            'sort_order' => 10,
            'storage_relation' => 'accommodation',
            'storage_column' => 'cabins',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        $toilet = BoatField::create([
            'internal_key' => 'toilet',
            'labels_json' => ['en' => 'Toilet'],
            'field_type' => 'tri_state',
            'block_key' => 'interior',
            'step_key' => 'specs',
            'sort_order' => 20,
            'storage_relation' => 'accommodation',
            'storage_column' => 'toilet',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        $engineType = BoatField::create([
            'internal_key' => 'engine_type',
            'labels_json' => ['en' => 'Engine Type'],
            'options_json' => [
                ['value' => 'inboard', 'labels' => ['en' => 'Inboard']],
                ['value' => 'outboard', 'labels' => ['en' => 'Outboard']],
            ],
            'field_type' => 'select',
            'block_key' => 'engine',
            'step_key' => 'specs',
            'sort_order' => 25,
            'storage_relation' => 'engine',
            'storage_column' => 'engine_type',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        $berths = BoatField::create([
            'internal_key' => 'berths',
            'labels_json' => ['en' => 'Berths'],
            'field_type' => 'number',
            'block_key' => 'interior',
            'step_key' => 'specs',
            'sort_order' => 30,
            'storage_relation' => 'accommodation',
            'storage_column' => 'berths',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        BoatFieldPriority::create([
            'field_id' => $toilet->id,
            'boat_type_key' => 'sailboat',
            'priority' => 'secondary',
        ]);

        BoatFieldPriority::create([
            'field_id' => $berths->id,
            'boat_type_key' => 'motorboat',
            'priority' => 'primary',
        ]);

        $response = $this->getJson('/api/boat-form-config?boat_type=sailboat&step=specs&locale=en');

        $response->assertOk()
            ->assertJsonPath('data.boat_type', 'sailboat')
            ->assertJsonPath('data.step', 'specs');

        $blocks = collect($response->json('data.blocks'));

        $interiorBlock = $blocks->firstWhere('block_key', 'interior');
        $engineBlock = $blocks->firstWhere('block_key', 'engine');

        $this->assertNotNull($interiorBlock);
        $this->assertNotNull($engineBlock);
        $this->assertSame($cabins->internal_key, data_get($interiorBlock, 'primary_fields.0.internal_key'));
        $this->assertSame($toilet->internal_key, data_get($interiorBlock, 'secondary_fields.0.internal_key'));
        $this->assertSame(1, data_get($interiorBlock, 'secondary_count'));
        $this->assertSame($engineType->internal_key, data_get($engineBlock, 'primary_fields.0.internal_key'));
        $this->assertSame('inboard', data_get($engineBlock, 'primary_fields.0.options.0.value'));
        $this->assertSame('Inboard', data_get($engineBlock, 'primary_fields.0.options.0.label'));

        $returnedFieldKeys = collect(data_get($interiorBlock, 'primary_fields', []))
            ->pluck('internal_key')
            ->merge(collect(data_get($interiorBlock, 'secondary_fields', []))->pluck('internal_key'))
            ->all();

        $this->assertNotContains('berths', $returnedFieldKeys);

        $responseWithoutBoatType = $this->getJson('/api/boat-form-config?step=specs&locale=en');
        $responseWithoutBoatType->assertOk();

        $interiorWithoutBoatType = collect($responseWithoutBoatType->json('data.blocks'))
            ->firstWhere('block_key', 'interior');

        $this->assertNotNull($interiorWithoutBoatType);
        $this->assertSame([$cabins->internal_key], collect(data_get($interiorWithoutBoatType, 'primary_fields', []))->pluck('internal_key')->all());
        $this->assertSame([], collect(data_get($interiorWithoutBoatType, 'secondary_fields', []))->pluck('internal_key')->all());
    }

    public function test_admin_can_manage_boat_fields_and_mappings(): void
    {
        $admin = User::factory()->create([
            'type' => UserType::ADMIN,
        ]);
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/admin/boat-fields', [
            'internal_key' => 'fuel_type',
            'labels_json' => [
                'en' => 'Fuel Type',
                'nl' => 'Brandstof type',
            ],
            'options_json' => [
                ['value' => 'diesel', 'label' => 'Diesel'],
                ['value' => 'petrol', 'label' => 'Petrol'],
            ],
            'field_type' => 'select',
            'block_key' => 'engine',
            'step_key' => 'specs',
            'sort_order' => 10,
            'storage_relation' => 'engine',
            'storage_column' => 'fuel',
            'ai_relevance' => true,
            'is_active' => true,
            'priorities' => [
                ['boat_type_key' => 'motorboat', 'priority' => 'primary'],
                ['boat_type_key' => 'sailboat', 'priority' => 'secondary'],
            ],
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.internal_key', 'fuel_type')
            ->assertJsonPath('data.priorities.0.boat_type_key', 'motorboat')
            ->assertJsonPath('data.options_json.0.value', 'diesel');

        $fieldId = $create->json('data.id');

        $mappings = $this->putJson("/api/admin/boat-fields/{$fieldId}/mappings", [
            'source' => 'scrape',
            'mappings' => [
                [
                    'external_value' => 'benzine',
                    'normalized_value' => 'petrol',
                    'match_type' => 'exact',
                ],
                [
                    'external_value' => '2x diesel',
                    'normalized_value' => 'diesel',
                    'match_type' => 'contains',
                ],
            ],
        ]);

        $mappings->assertOk()
            ->assertJsonPath('data.source', 'scrape')
            ->assertJsonCount(2, 'data.mappings');

        $index = $this->getJson('/api/admin/boat-fields');
        $index->assertOk()
            ->assertJsonPath('data.0.internal_key', 'fuel_type');

        $detail = $this->getJson("/api/admin/boat-fields/{$fieldId}/mappings?source=scrape");
        $detail->assertOk()
            ->assertJsonCount(2, 'data.mappings')
            ->assertJsonPath('data.source_summary.0.source', 'yachtshift')
            ->assertJsonPath('data.source_summary.1.source', 'scrape')
            ->assertJsonPath('data.source_summary.1.mappings_count', 2);

        $this->assertDatabaseHas('boat_fields', [
            'id' => $fieldId,
            'internal_key' => 'fuel_type',
            'storage_relation' => 'engine',
            'storage_column' => 'fuel',
        ]);

        $this->assertDatabaseHas('boat_field_priorities', [
            'field_id' => $fieldId,
            'boat_type_key' => 'motorboat',
            'priority' => 'primary',
        ]);

        $this->assertDatabaseHas('boat_field_mappings', [
            'field_id' => $fieldId,
            'source' => 'scrape',
            'external_value' => 'benzine',
            'normalized_value' => 'petrol',
            'match_type' => 'exact',
        ]);
    }

    public function test_admin_field_index_returns_usage_totals_and_mapping_summary(): void
    {
        $admin = User::factory()->create([
            'type' => UserType::ADMIN,
        ]);
        Sanctum::actingAs($admin);

        $field = BoatField::create([
            'internal_key' => 'fuel',
            'labels_json' => ['en' => 'Fuel'],
            'field_type' => 'text',
            'block_key' => 'engine',
            'step_key' => 'specs',
            'sort_order' => 10,
            'storage_relation' => 'engine',
            'storage_column' => 'fuel',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        BoatFieldMapping::create([
            'field_id' => $field->id,
            'source' => 'scrape',
            'external_value' => 'benzine',
            'normalized_value' => 'petrol',
            'match_type' => 'exact',
        ]);

        BoatFieldValueObservation::create([
            'field_id' => $field->id,
            'source' => 'scrape',
            'external_value' => 'benzine',
            'observed_count' => 12,
        ]);

        BoatFieldValueObservation::create([
            'field_id' => $field->id,
            'source' => 'scrape',
            'external_value' => 'diesel',
            'observed_count' => 8,
        ]);

        $response = $this->getJson('/api/admin/boat-fields');

        $response->assertOk()
            ->assertJsonPath('data.0.mappings_count', 1)
            ->assertJsonPath('data.0.value_observations_count', 2)
            ->assertJsonPath('data.0.value_observations_total', 20);
    }

    public function test_admin_can_generate_ai_mapping_drafts_across_all_sources(): void
    {
        config()->set('services.openai.key', 'test-openai-key');

        $admin = User::factory()->create([
            'type' => UserType::ADMIN,
        ]);
        Sanctum::actingAs($admin);

        $field = BoatField::create([
            'internal_key' => 'cooking_fuel',
            'labels_json' => ['en' => 'Cooking Fuel', 'nl' => 'Kookbrandstof'],
            'options_json' => [
                ['value' => 'gas', 'label' => 'Gas'],
                ['value' => 'electric', 'label' => 'Electric'],
                ['value' => 'diesel', 'label' => 'Diesel'],
            ],
            'field_type' => 'select',
            'block_key' => 'comfort',
            'step_key' => 'specs',
            'sort_order' => 10,
            'storage_relation' => 'comfort',
            'storage_column' => 'cooking_fuel',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        BoatFieldMapping::create([
            'field_id' => $field->id,
            'source' => 'scrape',
            'external_value' => 'gas cooker',
            'normalized_value' => 'gas',
            'match_type' => 'exact',
        ]);

        BoatFieldValueObservation::create([
            'field_id' => $field->id,
            'source' => 'yachtshift',
            'external_value' => 'diesel stove',
            'observed_count' => 4,
        ]);

        BoatFieldValueObservation::create([
            'field_id' => $field->id,
            'source' => 'scrape',
            'external_value' => 'gas cooker',
            'observed_count' => 6,
        ]);

        BoatFieldValueObservation::create([
            'field_id' => $field->id,
            'source' => 'future_import',
            'external_value' => 'elektrisch',
            'observed_count' => 3,
        ]);

        $yachtId = DB::table('yachts')->insertGetId([
            'vessel_id' => 'MAP-001',
            'boat_name' => 'Mapping Draft Test',
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('yacht_comfort')->insert([
            'yacht_id' => $yachtId,
            'cooking_fuel' => 'gas',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'yachtshift' => [
                                [
                                    'external_value' => 'diesel stove',
                                    'normalized_value' => 'diesel',
                                ],
                            ],
                            'scrape' => [],
                            'future_import' => [
                                [
                                    'external_value' => 'elektrisch',
                                    'normalized_value' => 'electric',
                                ],
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->postJson("/api/admin/boat-fields/{$field->id}/mappings/generate-ai");

        $response->assertOk()
            ->assertJsonPath('data.field_id', $field->id)
            ->assertJsonPath('data.created_mappings', 2)
            ->assertJsonPath('data.created_by_source.yachtshift', 1)
            ->assertJsonPath('data.created_by_source.scrape', 0)
            ->assertJsonPath('data.created_by_source.future_import', 1);

        $this->assertDatabaseHas('boat_field_mappings', [
            'field_id' => $field->id,
            'source' => 'yachtshift',
            'external_value' => 'diesel stove',
            'normalized_value' => 'diesel',
            'match_type' => 'exact',
        ]);

        $this->assertDatabaseHas('boat_field_mappings', [
            'field_id' => $field->id,
            'source' => 'future_import',
            'external_value' => 'elektrisch',
            'normalized_value' => 'electric',
            'match_type' => 'exact',
        ]);

        $this->assertDatabaseHas('boat_field_mappings', [
            'field_id' => $field->id,
            'source' => 'scrape',
            'external_value' => 'gas cooker',
            'normalized_value' => 'gas',
            'match_type' => 'exact',
        ]);

        $this->assertSame(3, BoatFieldMapping::query()->where('field_id', $field->id)->count());

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.openai.com/v1/chat/completions') {
                return false;
            }

            $content = (string) data_get($request->data(), 'messages.1.content', '');
            $payload = json_decode($content, true);

            return data_get($request->data(), 'model') === 'gpt-4o-mini'
                && data_get($payload, 'all_observations.yachtshift.0.external_value') === 'diesel stove'
                && data_get($payload, 'all_observations.scrape.0.external_value') === 'gas cooker'
                && data_get($payload, 'all_observations.future_import.0.external_value') === 'elektrisch'
                && data_get($payload, 'observations_to_map.yachtshift.0.external_value') === 'diesel stove'
                && data_get($payload, 'observations_to_map.scrape', []) === []
                && data_get($payload, 'observations_to_map.future_import.0.external_value') === 'elektrisch'
                && collect(data_get($payload, 'db_existing_values', []))->pluck('value')->contains('gas')
                && collect(data_get($payload, 'normalized_candidates', []))->pluck('value')->contains('electric');
        });
    }

    public function test_admin_can_fill_missing_help_defaults_for_all_fields(): void
    {
        $admin = User::factory()->create([
            'type' => UserType::ADMIN,
        ]);
        Sanctum::actingAs($admin);

        $missingHelpField = BoatField::create([
            'internal_key' => 'cooker',
            'labels_json' => ['en' => 'Cooker', 'nl' => 'Kooktoestel'],
            'field_type' => 'text',
            'block_key' => 'comfort',
            'step_key' => 'specs',
            'sort_order' => 10,
            'storage_relation' => 'comfort',
            'storage_column' => 'cooker',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        $partialHelpField = BoatField::create([
            'internal_key' => 'freezer',
            'labels_json' => ['en' => 'Freezer', 'nl' => 'Vriezer'],
            'help_json' => ['en' => 'Keep the custom English text.'],
            'field_type' => 'tri_state',
            'block_key' => 'comfort',
            'step_key' => 'specs',
            'sort_order' => 20,
            'storage_relation' => 'comfort',
            'storage_column' => 'freezer',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        $completeHelpField = BoatField::create([
            'internal_key' => 'microwave',
            'labels_json' => ['en' => 'Microwave'],
            'help_json' => [
                'nl' => 'Bestaande NL hulptekst.',
                'en' => 'Existing EN help text.',
                'de' => 'Bestehender DE Hilfetext.',
            ],
            'field_type' => 'tri_state',
            'block_key' => 'comfort',
            'step_key' => 'specs',
            'sort_order' => 30,
            'storage_relation' => 'comfort',
            'storage_column' => 'microwave',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/admin/boat-fields/fill-help-defaults');

        $response->assertOk()
            ->assertJsonPath('data.updated_fields', 2)
            ->assertJsonPath('data.skipped_fields', 1)
            ->assertJsonPath('data.overwrite', false);

        $missingHelpField->refresh();
        $partialHelpField->refresh();
        $completeHelpField->refresh();

        $this->assertNotEmpty(trim((string) data_get($missingHelpField->help_json, 'nl')));
        $this->assertNotEmpty(trim((string) data_get($missingHelpField->help_json, 'en')));
        $this->assertNotEmpty(trim((string) data_get($missingHelpField->help_json, 'de')));

        $this->assertSame(
            'Keep the custom English text.',
            data_get($partialHelpField->help_json, 'en'),
        );
        $this->assertNotEmpty(trim((string) data_get($partialHelpField->help_json, 'nl')));
        $this->assertNotEmpty(trim((string) data_get($partialHelpField->help_json, 'de')));

        $this->assertSame(
            'Existing EN help text.',
            data_get($completeHelpField->help_json, 'en'),
        );
    }

    public function test_admin_can_generate_missing_help_with_ai_in_bulk(): void
    {
        $admin = User::factory()->create([
            'type' => UserType::ADMIN,
        ]);
        Sanctum::actingAs($admin);

        $cooker = BoatField::create([
            'internal_key' => 'cooker',
            'labels_json' => ['en' => 'Cooker'],
            'field_type' => 'text',
            'block_key' => 'comfort',
            'step_key' => 'specs',
            'sort_order' => 10,
            'storage_relation' => 'comfort',
            'storage_column' => 'cooker',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        $freezer = BoatField::create([
            'internal_key' => 'freezer',
            'labels_json' => ['en' => 'Freezer'],
            'help_json' => ['en' => 'Keep my existing English help.'],
            'field_type' => 'tri_state',
            'block_key' => 'comfort',
            'step_key' => 'specs',
            'sort_order' => 20,
            'storage_relation' => 'comfort',
            'storage_column' => 'freezer',
            'ai_relevance' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'cooker' => [
                                'nl' => 'AI NL cooker help.',
                                'en' => 'AI EN cooker help.',
                                'de' => 'AI DE cooker help.',
                            ],
                            'freezer' => [
                                'nl' => 'AI NL freezer help.',
                                'en' => 'AI EN freezer help.',
                                'de' => 'AI DE freezer help.',
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/admin/boat-fields/generate-help-bulk');

        $response->assertOk()
            ->assertJsonPath('data.updated_fields', 2)
            ->assertJsonPath('data.skipped_fields', 0)
            ->assertJsonPath('data.overwrite', false);

        $cooker->refresh();
        $freezer->refresh();

        $this->assertSame('AI EN cooker help.', data_get($cooker->help_json, 'en'));
        $this->assertSame(
            'Keep my existing English help.',
            data_get($freezer->help_json, 'en'),
        );
        $this->assertSame('AI NL freezer help.', data_get($freezer->help_json, 'nl'));
        $this->assertSame('AI DE freezer help.', data_get($freezer->help_json, 'de'));
    }

    public function test_boat_field_seeder_populates_dynamic_specs_blocks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->seed(BoatFieldSeeder::class);

        $motorboatResponse = $this->getJson('/api/boat-form-config?boat_type=motorboat&step=specs&locale=en');
        $motorboatResponse->assertOk();

        $motorboatBlocks = collect($motorboatResponse->json('data.blocks'));
        $this->assertNotNull($motorboatBlocks->firstWhere('block_key', 'hull_dimensions'));
        $this->assertNotNull($motorboatBlocks->firstWhere('block_key', 'engine'));
        $this->assertNotNull($motorboatBlocks->firstWhere('block_key', 'interior'));
        $this->assertNull($motorboatBlocks->firstWhere('block_key', 'rigging'));

        $engineTypeField = collect(data_get($motorboatBlocks->firstWhere('block_key', 'engine'), 'primary_fields', []))
            ->firstWhere('internal_key', 'engine_type');

        $this->assertNotNull($engineTypeField);
        $this->assertSame('inboard', data_get($engineTypeField, 'options.0.value'));

        $sailboatResponse = $this->getJson('/api/boat-form-config?boat_type=sailboat&step=specs&locale=en');
        $sailboatResponse->assertOk();

        $sailboatBlocks = collect($sailboatResponse->json('data.blocks'));
        $this->assertNotNull($sailboatBlocks->firstWhere('block_key', 'rigging'));

        $riggingPrimaryKeys = collect(data_get($sailboatBlocks->firstWhere('block_key', 'rigging'), 'primary_fields', []))
            ->pluck('internal_key')
            ->all();

        $this->assertContains('main_sail', $riggingPrimaryKeys);
        $this->assertContains('winches', $riggingPrimaryKeys);
    }

    public function test_observation_backfill_command_collects_existing_imported_values(): void
    {
        $this->seed(BoatFieldSeeder::class);
        $this->seed(BoatFieldMappingSeeder::class);

        $fuelField = BoatField::query()->firstWhere('internal_key', 'fuel');
        $this->assertNotNull($fuelField);

        $yachtshiftYachtId = DB::table('yachts')->insertGetId([
            'vessel_id' => 'YS-T-001',
            'boat_name' => 'YachtShift Boat',
            'source' => 'yachtshift',
            'created_at' => now(),
            'updated_at' => now()->subDay(),
        ]);

        $scrapedYachtId = DB::table('yachts')->insertGetId([
            'vessel_id' => 'SC-T-001',
            'boat_name' => 'Scraped Boat',
            'source' => 'schepenkring_sold_archive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('yacht_engines')->insert([
            [
                'yacht_id' => $yachtshiftYachtId,
                'fuel' => 'Diesel',
                'created_at' => now(),
                'updated_at' => now()->subDay(),
            ],
            [
                'yacht_id' => $scrapedYachtId,
                'fuel' => 'benzine',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('app:backfill-boat-field-observations --reset')
            ->assertExitCode(0);

        $this->assertDatabaseHas('boat_field_value_observations', [
            'field_id' => $fuelField->id,
            'source' => 'yachtshift',
            'external_value' => 'Diesel',
            'observed_count' => 1,
        ]);

        $this->assertDatabaseHas('boat_field_value_observations', [
            'field_id' => $fuelField->id,
            'source' => 'scrape',
            'external_value' => 'benzine',
            'observed_count' => 1,
        ]);
    }
}
