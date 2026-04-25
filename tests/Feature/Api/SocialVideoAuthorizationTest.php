<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Jobs\RenderMarketingVideo;
use App\Models\Location;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPost;
use App\Models\Yacht;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

test('clients only see their own social videos and posts', function () {
    [$owner, $location] = socialAccessClientWithLocation('Owner Social Marina', 'OSM');
    [$intruder] = socialAccessClientWithLocation('Intruder Social Marina', 'ISM');

    $ownerYacht = socialAccessYacht($owner, $location, 'SK-SOC-OWN');
    $ownerVideo = Video::create([
        'yacht_id' => $ownerYacht->id,
        'status' => 'ready',
        'template_type' => 'vertical_slideshow_v1',
    ]);
    VideoPost::create([
        'video_id' => $ownerVideo->id,
        'status' => 'scheduled',
        'scheduled_at' => now()->addHour(),
        'publishers' => ['facebook'],
    ]);

    Sanctum::actingAs($intruder);

    $this->getJson("/api/social/videos?yacht_id={$ownerYacht->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/social/posts')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    Sanctum::actingAs($owner);

    $this->getJson("/api/social/videos?yacht_id={$ownerYacht->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownerVideo->id);

    $this->getJson('/api/social/posts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.video_id', $ownerVideo->id);
});

test('clients cannot schedule another clients social video', function () {
    [$owner, $location] = socialAccessClientWithLocation('Schedule Owner Marina', 'SOM');
    [$intruder] = socialAccessClientWithLocation('Schedule Intruder Marina', 'SIM');

    $ownerYacht = socialAccessYacht($owner, $location, 'SK-SOC-SCH');
    $ownerVideo = Video::create([
        'yacht_id' => $ownerYacht->id,
        'status' => 'ready',
        'template_type' => 'vertical_slideshow_v1',
    ]);

    Sanctum::actingAs($intruder);

    $this->postJson('/api/social/schedule', [
        'start_date' => now()->toDateString(),
        'cadence' => 'daily',
        'time' => '10:30',
        'video_ids' => [$ownerVideo->id],
        'publishers' => ['facebook'],
    ])->assertForbidden();

    expect(VideoPost::count())->toBe(0);
});

test('clients cannot retry or regenerate another clients social assets', function () {
    Queue::fake();

    [$owner, $location] = socialAccessClientWithLocation('Action Owner Marina', 'AOM');
    [$intruder] = socialAccessClientWithLocation('Action Intruder Marina', 'AIM');

    $ownerYacht = socialAccessYacht($owner, $location, 'SK-SOC-ACT');
    $ownerVideo = Video::create([
        'yacht_id' => $ownerYacht->id,
        'status' => 'ready',
        'template_type' => 'vertical_slideshow_v1',
    ]);
    $ownerPost = VideoPost::create([
        'video_id' => $ownerVideo->id,
        'status' => 'failed',
        'scheduled_at' => now()->subHour(),
        'publishers' => ['facebook'],
        'error_message' => 'Simulated failure',
    ]);

    Sanctum::actingAs($intruder);

    $this->postJson("/api/social/posts/{$ownerPost->id}/retry")
        ->assertForbidden();

    $this->patchJson("/api/social/posts/{$ownerPost->id}/reschedule", [
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ])->assertForbidden();

    $this->postJson("/api/social/videos/{$ownerVideo->id}/regenerate")
        ->assertForbidden();

    Queue::assertNotPushed(RenderMarketingVideo::class);
});

function socialAccessClientWithLocation(string $name, string $code): array
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

function socialAccessYacht(User $owner, Location $location, string $vesselId): Yacht
{
    return Yacht::create([
        'user_id' => $owner->id,
        'location_id' => $location->id,
        'vessel_id' => $vesselId,
        'boat_name' => 'Social Access Yacht',
        'status' => 'active',
    ]);
}
