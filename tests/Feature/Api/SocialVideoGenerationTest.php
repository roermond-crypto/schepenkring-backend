<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Jobs\RenderMarketingVideo;
use App\Models\Location;
use App\Models\User;
use App\Models\Video;
use App\Models\Yacht;
use App\Models\YachtImage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

test('staff can queue a generated social video from yacht images', function () {
    Queue::fake();
    Storage::fake('public');

    $location = Location::create([
        'name' => 'Amsterdam Marina',
        'code' => 'AMS',
        'status' => 'ACTIVE',
    ]);

    $employee = socialVideoStaffForLocation($location);
    $yacht = socialVideoYacht($location->id);

    Storage::disk('public')->put('approved/master/yacht-video-1.jpg', 'image');

    YachtImage::create([
        'yacht_id' => $yacht->id,
        'optimized_master_url' => 'approved/master/yacht-video-1.jpg',
        'status' => 'approved',
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/social/videos/generate', [
        'yacht_id' => $yacht->id,
    ]);

    $response->assertAccepted()
        ->assertJsonPath('message', 'Video generation queued')
        ->assertJsonPath('video.yacht_id', $yacht->id)
        ->assertJsonPath('video.status', 'queued')
        ->assertJsonPath('renderable_image_count', 1);

    $videoId = $response->json('video.id');

    $this->assertDatabaseHas('videos', [
        'id' => $videoId,
        'yacht_id' => $yacht->id,
        'status' => 'queued',
    ]);

    Queue::assertPushed(RenderMarketingVideo::class, function (RenderMarketingVideo $job) {
        return $job->queue === 'video-rendering';
    });

    $this->getJson("/api/social/videos?yacht_id={$yacht->id}")
        ->assertOk()
        ->assertJsonFragment([
            'id' => $videoId,
            'yacht_id' => $yacht->id,
            'status' => 'queued',
        ]);
});

test('generate social video returns existing queued video by default', function () {
    Queue::fake();
    Storage::fake('public');

    $location = Location::create([
        'name' => 'Rotterdam Harbor',
        'code' => 'RTM',
        'status' => 'ACTIVE',
    ]);

    $employee = socialVideoStaffForLocation($location);
    $yacht = socialVideoYacht($location->id);

    $existing = Video::create([
        'yacht_id' => $yacht->id,
        'status' => 'queued',
        'template_type' => 'vertical_slideshow_v1',
    ]);

    Storage::disk('public')->put('approved/master/yacht-video-2.jpg', 'image');

    YachtImage::create([
        'yacht_id' => $yacht->id,
        'optimized_master_url' => 'approved/master/yacht-video-2.jpg',
        'status' => 'approved',
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($employee);

    $response = $this->postJson('/api/social/videos/generate', [
        'yacht_id' => $yacht->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Existing generated video returned')
        ->assertJsonPath('video.id', $existing->id);

    expect(Video::count())->toBe(1);
    Queue::assertNothingPushed();
});

test('generate social video rejects yachts without usable images', function () {
    Queue::fake();
    Storage::fake('public');

    $location = Location::create([
        'name' => 'Den Haag Harbor',
        'code' => 'DHA',
        'status' => 'ACTIVE',
    ]);

    $employee = socialVideoStaffForLocation($location);
    $yacht = socialVideoYacht($location->id);

    Sanctum::actingAs($employee);

    $this->postJson('/api/social/videos/generate', [
        'yacht_id' => $yacht->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'No usable boat images found for video generation.');

    expect(Video::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('staff cannot queue a generated social video for an inaccessible yacht', function () {
    Queue::fake();
    Storage::fake('public');

    $locationA = Location::create([
        'name' => 'Groningen Harbor',
        'code' => 'GRN',
        'status' => 'ACTIVE',
    ]);

    $locationB = Location::create([
        'name' => 'Utrecht Harbor',
        'code' => 'UTR',
        'status' => 'ACTIVE',
    ]);

    $employee = socialVideoStaffForLocation($locationA);
    $yacht = socialVideoYacht($locationB->id);

    Storage::disk('public')->put('approved/master/yacht-video-3.jpg', 'image');

    YachtImage::create([
        'yacht_id' => $yacht->id,
        'optimized_master_url' => 'approved/master/yacht-video-3.jpg',
        'status' => 'approved',
        'sort_order' => 1,
    ]);

    Sanctum::actingAs($employee);

    $this->postJson('/api/social/videos/generate', [
        'yacht_id' => $yacht->id,
    ])->assertForbidden();

    expect(Video::count())->toBe(0);
    Queue::assertNothingPushed();
});

function socialVideoStaffForLocation(Location $location): User
{
    $employee = User::factory()->create([
        'type' => UserType::EMPLOYEE,
        'status' => UserStatus::ACTIVE,
    ]);

    $employee->locations()->attach($location->id, ['role' => 'sales']);

    return $employee;
}

function socialVideoYacht(int $locationId): Yacht
{
    return Yacht::create([
        'boat_name' => 'Sea Ray 320',
        'status' => 'active',
        'ref_harbor_id' => $locationId,
    ]);
}
