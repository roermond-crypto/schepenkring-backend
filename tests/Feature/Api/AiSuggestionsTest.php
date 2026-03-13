<?php

use App\Services\PineconeMatcherService;

test('ai suggestions sanitizes invalid values and drops metadata-only matches', function () {
    app()->instance(PineconeMatcherService::class, new class extends PineconeMatcherService {
        public function __construct()
        {
        }

        public function matchAndBuildConsensus(array $formValues, ?string $hintText = null): array
        {
            return [
                'consensus_values' => [
                    'year' => 1994,
                    'loa' => 0,
                    'beam' => 0,
                    'draft' => 100,
                    'fuel' => 'Diesel',
                    'price' => 0,
                    'horse_power' => 260,
                    'engine_manufacturer' => 'MerCruiser',
                ],
                'field_confidence' => [
                    'year' => 0.89,
                    'loa' => 0.98,
                    'beam' => 0.98,
                    'draft' => 0.89,
                    'fuel' => 0.89,
                    'price' => 0.98,
                    'horse_power' => 0.74,
                    'engine_manufacturer' => 0.85,
                ],
                'field_sources' => [
                    'year' => 'pinecone_consensus',
                    'loa' => 'pinecone_consensus',
                    'beam' => 'pinecone_consensus',
                    'draft' => 'pinecone_consensus',
                    'fuel' => 'pinecone_consensus',
                    'price' => 'pinecone_consensus',
                    'horse_power' => 'pinecone_consensus',
                    'engine_manufacturer' => 'pinecone_consensus',
                ],
                'top_matches' => [
                    [
                        'score' => 72,
                        'boat' => [
                            'manufacturer' => 'Bayliner',
                            'model' => '3288',
                            'boat_type' => 'Motor Yacht',
                            'year' => 1994,
                            'loa' => 0,
                            'beam' => 0,
                            'draft' => 100,
                            'fuel' => 'Diesel',
                            'price' => 0,
                        ],
                    ],
                    [
                        'score' => 56,
                        'boat' => [
                            'boat_ref' => '196571',
                            'source_feed_url' => 'feed.xml',
                            'synced_at_utc' => '2026-02-27T19:40:35+00:00',
                        ],
                    ],
                ],
                'warnings' => [],
            ];
        }
    });

    $response = $this->postJson('/api/ai/suggestions', [
        'query' => 'Bayliner 3288',
    ]);

    $response->assertOk();
    $response->assertJsonPath('consensus_values.year', 1994);
    $response->assertJsonMissingPath('consensus_values.loa');
    $response->assertJsonMissingPath('consensus_values.beam');
    $response->assertJsonMissingPath('consensus_values.price');
    expect((float) $response->json('consensus_values.draft'))->toBe(1.0);
    $response->assertJsonPath('consensus_values.fuel', 'Diesel');
    $response->assertJsonPath('consensus_values.horse_power', 260);
    $response->assertJsonPath('consensus_values.engine_manufacturer', 'MerCruiser');
    $response->assertJsonMissingPath('field_confidence.loa');
    $response->assertJsonCount(1, 'top_matches');
    $response->assertJsonPath('top_matches.0.boat.manufacturer', 'Bayliner');
    expect((float) $response->json('top_matches.0.boat.draft'))->toBe(1.0);
    $response->assertJsonMissingPath('top_matches.0.boat.price');
});
