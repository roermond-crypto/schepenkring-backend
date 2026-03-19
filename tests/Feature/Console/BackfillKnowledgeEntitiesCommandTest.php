<?php

use App\Models\KnowledgeEntity;
use App\Models\Location;
use App\Models\Yacht;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('knowledge backfill command builds harbor and yacht entities with relationships', function () {
    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
        'chat_widget_enabled' => true,
        'chat_widget_welcome_text' => 'Welcome to the NauticSecure harbor desk.',
    ]);

    $yacht = Yacht::create([
        'boat_name' => 'Grand Soleil 40',
        'manufacturer' => 'Grand Soleil',
        'model' => '40',
        'status' => 'available',
        'ref_harbor_id' => $location->id,
        'location_city' => 'Amsterdam',
    ]);

    $this->artisan('app:backfill-knowledge-entities')
        ->expectsOutput('Knowledge entity backfill completed.')
        ->expectsOutput('Types: location, yacht')
        ->expectsOutput('Locations synced: 1')
        ->expectsOutput('Yachts synced: 1')
        ->assertExitCode(0);

    $locationEntity = KnowledgeEntity::query()
        ->where('type', 'location')
        ->where('source_table', 'locations')
        ->where('source_id', $location->id)
        ->first();

    $yachtEntity = KnowledgeEntity::query()
        ->where('type', 'yacht')
        ->where('source_table', 'yachts')
        ->where('source_id', $yacht->id)
        ->first();

    expect($locationEntity)->not->toBeNull();
    expect($yachtEntity)->not->toBeNull();
    expect($yachtEntity->location_id)->toBe($location->id);
    expect(data_get($locationEntity->metadata, 'code'))->toBe('AMS');

    $relationship = $yachtEntity->outgoingRelationships()
        ->where('relationship_type', 'located_at')
        ->first();

    expect($relationship)->not->toBeNull();
    expect($relationship->toEntity)->not->toBeNull();
    expect($relationship->toEntity->source_id)->toBe($location->id);
});
