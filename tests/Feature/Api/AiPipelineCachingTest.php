<?php

namespace Tests\Feature\Api;

use App\Models\Yacht;
use App\Models\YachtAiExtraction;
use App\Models\YachtImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AiPipelineCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_extract_reuses_cached_result_for_unchanged_yacht_inputs(): void
    {
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.openai.key', null);

        Storage::fake('public');
        Storage::disk('public')->put('tests/yacht.jpg', 'fake-image-bytes');

        $yacht = Yacht::create([
            'vessel_id' => (string) Str::uuid(),
            'boat_name' => 'Cache Test',
            'status' => 'draft',
        ]);

        YachtImage::create([
            'yacht_id' => $yacht->id,
            'url' => 'tests/yacht.jpg',
            'status' => 'approved',
            'sort_order' => 1,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'boat_name' => 'Beneteau Oceanis 35',
                                'manufacturer' => 'Beneteau',
                                'model' => 'Oceanis 35',
                                'year' => 2018,
                                'loa' => '10.45',
                                'hull_type' => 'mono',
                                'confidence' => [
                                    'boat_name' => 0.95,
                                    'manufacturer' => 0.95,
                                    'model' => 0.95,
                                    'year' => 0.90,
                                    'loa' => 0.90,
                                    'hull_type' => 0.90,
                                ],
                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $payload = [
            'yacht_id' => $yacht->id,
            'hint_text' => 'Beneteau Oceanis 35',
            'speed_mode' => 'fast',
        ];

        $firstResponse = $this->postJson('/api/ai/pipeline-extract', $payload);

        $firstResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.cache_hit', false)
            ->assertJsonPath('step2_form_values.boat_name', 'Beneteau Oceanis 35');

        Http::assertSentCount(2);

        $extraction = YachtAiExtraction::query()->latest('id')->first();
        $this->assertNotNull($extraction);
        $this->assertSame('fast', $extraction->meta_json['speed_mode'] ?? null);
        $this->assertNotEmpty($extraction->meta_json['input_signature'] ?? null);

        Http::fake(static function () {
            throw new RuntimeException('Cache hit should not call external HTTP services.');
        });

        $secondResponse = $this->postJson('/api/ai/pipeline-extract', $payload);

        $secondResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.cache_hit', true)
            ->assertJsonPath('meta.ai_session_id', $extraction->session_id)
            ->assertJsonPath('step2_form_values.boat_name', 'Beneteau Oceanis 35');

        $this->assertContains('cached_response', $secondResponse->json('meta.stages_run'));
    }

    public function test_balanced_mode_still_runs_enrichment_to_fill_more_step2_fields(): void
    {
        config()->set('services.gemini.key', 'test-gemini-key');
        config()->set('services.openai.key', 'test-openai-key');
        config()->set('services.pinecone.key', null);
        config()->set('services.pinecone.host', null);

        Storage::fake('public');
        Storage::disk('public')->put('tests/yacht-balanced.jpg', 'fake-image-bytes');

        $yacht = Yacht::create([
            'vessel_id' => (string) Str::uuid(),
            'boat_name' => 'Balanced Test',
            'status' => 'draft',
        ]);

        YachtImage::create([
            'yacht_id' => $yacht->id,
            'url' => 'tests/yacht-balanced.jpg',
            'status' => 'approved',
            'sort_order' => 1,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'boat_name' => 'Jeanneau Sun Odyssey 349',
                                    'manufacturer' => 'Jeanneau',
                                    'model' => 'Sun Odyssey 349',
                                    'year' => 2019,
                                    'loa' => '10.34',
                                    'hull_type' => 'mono',
                                    'confidence' => [
                                        'boat_name' => 0.95,
                                        'manufacturer' => 0.95,
                                        'model' => 0.95,
                                        'year' => 0.9,
                                        'loa' => 0.9,
                                        'hull_type' => 0.9,
                                    ],
                                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            ]],
                        ],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'beam' => '3.44',
                                    'draft' => '1.98',
                                    'cabins' => '3',
                                    'berths' => '6',
                                    'confidence' => [
                                        'beam' => 0.65,
                                        'draft' => 0.65,
                                        'cabins' => 0.6,
                                        'berths' => 0.6,
                                    ],
                                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            ]],
                        ],
                    ]],
                ], 200),
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'engine_manufacturer' => 'Yanmar',
                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ],
                    ]],
                ], 200)
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'heating' => 'yes',
                                'gps' => 'yes',
                                'fridge' => 'yes',
                                'toilet' => '1',
                                'shower' => '1',
                                'short_description_nl' => 'Ruime toerzeiler met moderne uitrusting.',
                                'confidence' => [
                                    'heating' => 0.62,
                                    'gps' => 0.64,
                                    'fridge' => 0.6,
                                    'toilet' => 0.66,
                                    'shower' => 0.61,
                                    'short_description_nl' => 0.7,
                                ],
                                'warnings' => [],
                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ],
                    ]],
                ], 200)
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'confirmed_fields' => [],
                                'removed_fields' => [],
                                'adjusted_values' => [],
                                'suggested_additions' => [],
                                'adjusted_confidence' => [],
                                'notes' => 'Validation complete',
                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ],
                    ]],
                ], 200),
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [[
                    'embedding' => array_fill(0, 8, 0.1234),
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/ai/pipeline-extract', [
            'yacht_id' => $yacht->id,
            'hint_text' => 'Jeanneau Sun Odyssey 349',
            'speed_mode' => 'balanced',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('step2_form_values.heating', 'yes')
            ->assertJsonPath('step2_form_values.gps', 'yes')
            ->assertJsonPath('step2_form_values.fridge', 'yes')
            ->assertJsonPath('step2_form_values.cabins', '3');

        $this->assertContains('gemini_db_enrichment', $response->json('meta.stages_run'));
        $this->assertContains('openai_world_knowledge_enrichment', $response->json('meta.stages_run'));
        $this->assertGreaterThan(10, $response->json('meta.filled_fields_count'));
    }
}
