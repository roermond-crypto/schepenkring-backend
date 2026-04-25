<?php

use App\Jobs\PublishVideoPost;
use App\Models\Location;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPost;
use App\Models\Yacht;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('publish scheduled command dispatches due posts only', function () {
    Queue::fake();

    $video = scheduledPublishReadyVideo('SK-CMD-DUE');

    VideoPost::create([
        'video_id' => $video->id,
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'publishers' => ['facebook'],
    ]);

    VideoPost::create([
        'video_id' => $video->id,
        'status' => 'scheduled',
        'scheduled_at' => now()->addHour(),
        'publishers' => ['facebook'],
    ]);

    $this->artisan('social:publish-scheduled', ['--limit' => 10])
        ->expectsOutput('Queued 1 scheduled post(s).')
        ->assertSuccessful();

    Queue::assertPushed(PublishVideoPost::class, 1);
});

test('publish scheduled command is registered in the scheduler', function () {
    $scheduledCommands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => (string) $event->command)
        ->filter();

    expect($scheduledCommands->contains(fn (string $command) => str_contains($command, 'social:publish-scheduled')))
        ->toBeTrue();
});

function scheduledPublishReadyVideo(string $vesselId): Video
{
    $location = Location::create([
        'name' => 'Scheduled Publish Marina '.$vesselId,
        'code' => substr($vesselId, -3),
        'status' => 'ACTIVE',
    ]);

    $owner = User::factory()->create();

    $yacht = Yacht::create([
        'user_id' => $owner->id,
        'location_id' => $location->id,
        'vessel_id' => $vesselId,
        'boat_name' => 'Scheduled Publish Yacht',
        'status' => 'active',
    ]);

    return Video::create([
        'yacht_id' => $yacht->id,
        'status' => 'ready',
        'template_type' => 'vertical_slideshow_v1',
    ]);
}
