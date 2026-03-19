<?php

use App\Services\KnowledgeVectorStoreService;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

it('upserts searches and deletes generic knowledge vectors through the shared store', function () {
    config()->set('services.openai.key', 'test-openai');
    config()->set('services.pinecone.key', 'test-pinecone');
    config()->set('services.pinecone.host', 'https://pinecone.test');
    config()->set('services.pinecone.namespace', 'knowledge');

    Http::fake([
        'https://api.openai.com/v1/embeddings' => Http::response([
            'data' => [[
                'embedding' => [0.12, 0.34, 0.56],
            ]],
        ], 200),
        'https://pinecone.test/vectors/upsert' => Http::response([
            'upsertedCount' => 1,
        ], 200),
        'https://pinecone.test/query' => Http::response([
            'matches' => [[
                'id' => 'harbor-entity-1',
                'score' => 0.91,
                'metadata' => [
                    'kind' => 'harbor',
                    'entity_id' => 1,
                    'location_id' => 7,
                ],
            ]],
        ], 200),
        'https://pinecone.test/vectors/delete' => Http::response([
            'status' => 'ok',
        ], 200),
    ]);

    $store = app(KnowledgeVectorStoreService::class);

    expect($store->upsertText('harbor-entity-1', 'Safe harbor with fuel and repair services', [
        'kind' => 'harbor',
        'entity_id' => 1,
        'location_id' => 7,
    ]))->toBeTrue();

    $matches = $store->search('safe harbor near the coast', 3, [
        'kind' => ['$eq' => 'harbor'],
    ]);

    expect($matches)->toHaveCount(1);
    expect(data_get($matches, '0.metadata.kind'))->toBe('harbor');
    expect(data_get($matches, '0.metadata.location_id'))->toBe(7);

    expect($store->delete('harbor-entity-1'))->toBeTrue();

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://pinecone.test/vectors/upsert') {
            return false;
        }

        return data_get($request->data(), 'namespace') === 'knowledge'
            && data_get($request->data(), 'vectors.0.metadata.kind') === 'harbor';
    });

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://pinecone.test/query') {
            return false;
        }

        return data_get($request->data(), 'namespace') === 'knowledge'
            && data_get($request->data(), 'filter.kind.$eq') === 'harbor';
    });

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://pinecone.test/vectors/delete') {
            return false;
        }

        return data_get($request->data(), 'ids.0') === 'harbor-entity-1';
    });
});
