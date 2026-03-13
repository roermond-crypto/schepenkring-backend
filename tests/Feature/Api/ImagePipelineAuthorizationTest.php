<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use App\Models\YachtImage;
use Laravel\Sanctum\Sanctum;

test('guests cannot access yacht image pipeline endpoints', function () {
    [$owner, $location] = imagePipelineClientWithLocation('Guest Owner Marina', 'GOM');
    $yacht = imagePipelineYacht($owner, $location);

    YachtImage::create([
        'yacht_id' => $yacht->id,
        'url' => 'approved/master/guest-owner.jpg',
        'status' => 'approved',
        'sort_order' => 1,
    ]);

    $this->getJson("/api/yachts/{$yacht->id}/images")->assertUnauthorized();
    $this->getJson("/api/yachts/{$yacht->id}/step2-unlocked")->assertUnauthorized();
});

test('clients cannot view another clients yacht images', function () {
    [$owner, $ownerLocation] = imagePipelineClientWithLocation('Owner Marina', 'OWN');
    [$intruder] = imagePipelineClientWithLocation('Intruder Marina', 'INT');

    $yacht = imagePipelineYacht($owner, $ownerLocation);

    YachtImage::create([
        'yacht_id' => $yacht->id,
        'url' => 'approved/master/owner-only.jpg',
        'status' => 'approved',
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($intruder);

    $this->getJson("/api/yachts/{$yacht->id}/images")->assertForbidden();
    $this->getJson("/api/yachts/{$yacht->id}/step2-unlocked")->assertForbidden();
});

test('clients can still view their own yacht images', function () {
    [$owner, $location] = imagePipelineClientWithLocation('Client Marina', 'CLI');
    $yacht = imagePipelineYacht($owner, $location);

    YachtImage::create([
        'yacht_id' => $yacht->id,
        'url' => 'approved/master/client-visible.jpg',
        'status' => 'approved',
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($owner);

    $this->getJson("/api/yachts/{$yacht->id}/images")
        ->assertOk()
        ->assertJsonPath('stats.total', 1)
        ->assertJsonPath('images.0.yacht_id', $yacht->id);

    $this->getJson("/api/yachts/{$yacht->id}/step2-unlocked")
        ->assertOk()
        ->assertJsonPath('step2_unlocked', true)
        ->assertJsonPath('approved_count', 1);
});

function imagePipelineClientWithLocation(string $name, string $code): array
{
    $location = Location::create([
        'name' => $name,
        'code' => $code,
        'status' => 'ACTIVE',
    ]);

    $user = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
    ]);

    return [$user, $location];
}

function imagePipelineYacht(User $owner, Location $location): Yacht
{
    return Yacht::create([
        'user_id' => $owner->id,
        'ref_harbor_id' => $location->id,
        'vessel_id' => 'SK-IMG-'.strtoupper(substr(md5((string) microtime(true)), 0, 6)),
        'boat_name' => 'Authorization Test Yacht',
        'status' => 'draft',
    ]);
}
