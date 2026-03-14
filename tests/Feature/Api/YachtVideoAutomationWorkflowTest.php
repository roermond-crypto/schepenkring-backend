<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Jobs\RenderMarketingVideo;
use App\Jobs\SendBoatVideoWhatsappJob;
use App\Models\HarborChannel;
use App\Models\Location;
use App\Models\User;
use App\Models\Video;
use App\Models\Yacht;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

test('published yacht with a main image auto queues a marketing video on create', function () {
    Queue::fake();
    Storage::fake('public');

    $location = Location::create([
        'name' => 'Auto Video Marina',
        'code' => 'AVM',
        'status' => 'ACTIVE',
    ]);

    $owner = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->post('/api/yachts', [
        'boat_name' => 'Automation Ready Yacht',
        'status' => 'active',
        'ref_harbor_id' => $location->id,
        'main_image' => UploadedFile::fake()->image('main.jpg'),
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated();

    $yachtId = $response->json('id');

    $this->assertDatabaseHas('videos', [
        'yacht_id' => $yachtId,
        'status' => 'queued',
        'generation_trigger' => 'created',
    ]);

    Queue::assertPushed(RenderMarketingVideo::class, function (RenderMarketingVideo $job) {
        return $job->queue === 'video-rendering';
    });
});

test('image pipeline upload auto queues a marketing video for a published yacht', function () {
    Queue::fake();
    Storage::fake('public');

    $location = Location::create([
        'name' => 'Gallery Trigger Marina',
        'code' => 'GTM',
        'status' => 'ACTIVE',
    ]);

    $owner = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
    ]);

    $yacht = Yacht::create([
        'user_id' => $owner->id,
        'ref_harbor_id' => $location->id,
        'boat_name' => 'Gallery Trigger Yacht',
        'status' => 'active',
    ]);

    Sanctum::actingAs($owner);

    $response = $this->post('/api/yachts/'.$yacht->id.'/images/upload', [
        'images' => [
            UploadedFile::fake()->image('gallery.jpg'),
        ],
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('videos', [
        'yacht_id' => $yacht->id,
        'status' => 'queued',
        'generation_trigger' => 'published',
    ]);

    Queue::assertPushed(RenderMarketingVideo::class, function (RenderMarketingVideo $job) {
        return $job->queue === 'video-rendering';
    });
});

test('boat video whatsapp job sends the ready video url to the owner and stores delivery state', function () {
    $location = Location::create([
        'name' => 'WhatsApp Marina',
        'code' => 'WAM',
        'status' => 'ACTIVE',
    ]);

    $owner = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
        'phone' => '+31612345678',
    ]);

    HarborChannel::create([
        'harbor_id' => $location->id,
        'channel' => 'whatsapp',
        'provider' => '360dialog',
        'from_number' => '31600000000',
        'api_key_encrypted' => 'live-api-key',
        'status' => 'active',
        'metadata' => [
            'base_url' => 'https://waba-v2.360dialog.io',
        ],
    ]);

    $yacht = Yacht::create([
        'user_id' => $owner->id,
        'ref_harbor_id' => $location->id,
        'boat_name' => 'WhatsApp Ready Yacht',
        'status' => 'active',
    ]);

    $video = Video::create([
        'yacht_id' => $yacht->id,
        'status' => 'ready',
        'video_url' => 'https://cdn.example.test/videos/whatsapp-ready-yacht.mp4',
        'template_type' => 'vertical_slideshow_v1',
        'whatsapp_status' => 'pending',
    ]);

    Http::fake([
        'https://waba-v2.360dialog.io/v1/messages' => Http::response([
            'messages' => [[
                'id' => 'wamid.video.1',
            ]],
        ], 200),
    ]);

    (new SendBoatVideoWhatsappJob($video->id))->handle(
        app(\App\Services\WhatsApp360DialogService::class),
        app(\App\Services\PhoneNumberService::class)
    );

    Http::assertSent(function ($request) {
        return $request->url() === 'https://waba-v2.360dialog.io/v1/messages'
            && data_get($request->data(), 'to') === '31612345678'
            && str_contains((string) data_get($request->data(), 'text.body'), 'WhatsApp Ready Yacht')
            && str_contains((string) data_get($request->data(), 'text.body'), 'https://cdn.example.test/videos/whatsapp-ready-yacht.mp4');
    });

    expect($video->fresh()->whatsapp_status)->toBe('sent');
    expect($video->fresh()->whatsapp_message_id)->toBe('wamid.video.1');
    expect($video->fresh()->whatsapp_recipient)->toBe('31612345678');
    expect($video->fresh()->whatsapp_sent_at)->not->toBeNull();
});

test('authorized users can manually queue owner whatsapp delivery from the social videos api', function () {
    Queue::fake();

    $location = Location::create([
        'name' => 'Manual Notify Marina',
        'code' => 'MNM',
        'status' => 'ACTIVE',
    ]);

    $owner = User::factory()->create([
        'type' => UserType::CLIENT,
        'status' => UserStatus::ACTIVE,
        'client_location_id' => $location->id,
    ]);

    $yacht = Yacht::create([
        'user_id' => $owner->id,
        'ref_harbor_id' => $location->id,
        'boat_name' => 'Manual Notify Yacht',
        'status' => 'active',
    ]);

    $video = Video::create([
        'yacht_id' => $yacht->id,
        'status' => 'ready',
        'video_url' => 'https://cdn.example.test/videos/manual-notify-yacht.mp4',
        'template_type' => 'vertical_slideshow_v1',
    ]);

    Sanctum::actingAs($owner);

    $this->postJson('/api/social/videos/'.$video->id.'/notify-owner', [
        'force' => true,
    ])->assertAccepted()
        ->assertJsonPath('message', 'Owner WhatsApp delivery queued')
        ->assertJsonPath('video.id', $video->id);

    Queue::assertPushed(SendBoatVideoWhatsappJob::class, function (SendBoatVideoWhatsappJob $job) use ($video) {
        return $job->videoId === $video->id && $job->force === true;
    });
});
