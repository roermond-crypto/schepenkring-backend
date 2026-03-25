<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\KnowledgeEntity;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use App\Services\BoatTaskAutomationService;
use App\Services\SyncYachtTasksService;
use App\Services\VideoAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(SyncYachtTasksService::class, new class {
        public function syncForYacht(Yacht $yacht, ?User $actor = null): void
        {
        }
    });

    app()->instance(BoatTaskAutomationService::class, new class {
        public function fireForYacht(Yacht $yacht, User $actor, bool $isUpdate = false): array
        {
            return [];
        }
    });

    app()->instance(VideoAutomationService::class, new class extends VideoAutomationService {
        public function __construct()
        {
        }

        public function handleYachtCreated(Yacht $yacht): ?\App\Models\Video
        {
            return null;
        }

        public function handleYachtPublished(Yacht $yacht): ?\App\Models\Video
        {
            return null;
        }
    });
});

test('admin harbor updates keep the location knowledge entity in sync', function () {
    $admin = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/admin/harbors', [
        'name' => 'Lelystad Marina',
        'code' => 'LEY',
        'status' => 'ACTIVE',
    ]);

    $createResponse->assertCreated()
        ->assertJsonPath('data.name', 'Lelystad Marina');

    $locationId = $createResponse->json('data.id');

    $entity = KnowledgeEntity::query()
        ->where('type', 'location')
        ->where('source_table', 'locations')
        ->where('source_id', $locationId)
        ->first();

    expect($entity)->not->toBeNull();
    expect($entity->title)->toBe('Lelystad Marina');
    expect(data_get($entity->metadata, 'code'))->toBe('LEY');

    $this->putJson("/api/admin/locations/{$locationId}/widget-settings", [
        'enabled' => true,
        'welcome_text' => 'Ask NauticSecure AI anything about this harbor.',
        'theme' => 'ocean',
    ])->assertOk();

    $this->patchJson("/api/admin/harbors/{$locationId}", [
        'name' => 'Lelystad Marina North',
    ])->assertOk();

    $entity = $entity->fresh();

    expect($entity->title)->toBe('Lelystad Marina North');
    expect(data_get($entity->metadata, 'chat_widget_enabled'))->toBeTrue();
    expect(data_get($entity->metadata, 'chat_widget_theme'))->toBe('ocean');
    expect(data_get($entity->metadata, 'chat_widget_welcome_text'))->toBe('Ask NauticSecure AI anything about this harbor.');
});

test('yacht saves create a knowledge entity linked to the selected harbor', function () {
    $location = Location::create([
        'name' => 'Lelystad Marina',
        'code' => 'LEY',
        'status' => 'ACTIVE',
    ]);

    $client = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
        'first_name' => 'Klaas',
        'last_name' => 'Jansen',
        'phone' => '+31611112222',
        'date_of_birth' => '1985-09-12',
        'address_line1' => 'Havenweg 12',
        'city' => 'Lelystad',
        'postal_code' => '8221AA',
        'country' => 'NL',
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($client);

    $response = $this->postJson('/api/yachts', [
        'boat_name' => 'Contest 42',
        'manufacturer' => 'Contest',
        'model' => '42CS',
        'status' => 'available',
        'price' => 265000,
        'location_city' => 'Lelystad',
        'short_description_en' => 'A bluewater cruiser prepared for safe family passages.',
        'ref_harbor_id' => $location->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('boat_name', 'Contest 42');

    $yachtId = $response->json('id');

    $entity = KnowledgeEntity::query()
        ->where('type', 'yacht')
        ->where('source_table', 'yachts')
        ->where('source_id', $yachtId)
        ->first();

    expect($entity)->not->toBeNull();
    expect($entity->location_id)->toBe($location->id);
    expect(data_get($entity->metadata, 'manufacturer'))->toBe('Contest');
    expect(data_get($entity->metadata, 'ref_harbor_id'))->toBe($location->id);

    $relationship = $entity->outgoingRelationships()
        ->where('relationship_type', 'located_at')
        ->first();

    expect($relationship)->not->toBeNull();
    expect($relationship->toEntity)->not->toBeNull();
    expect($relationship->toEntity->type)->toBe('location');
    expect($relationship->toEntity->source_id)->toBe($location->id);
});
