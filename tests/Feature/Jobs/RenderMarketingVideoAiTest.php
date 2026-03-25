<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Jobs\RenderMarketingVideo;
use App\Models\Location;
use App\Models\User;
use App\Models\Video;
use App\Models\Yacht;
use App\Models\YachtImage;
use App\Services\FFmpegService;
use App\Services\OpenAiVideoGenerationService;
use App\Services\VideoAutomationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('render marketing video can complete through the openai provider flow', function () {
    Storage::fake('public');
    Queue::fake();

    config()->set('video_automation.provider', 'openai_sora');
    config()->set('video_automation.auto_schedule', false);
    config()->set('video_automation.auto_notify_owner_whatsapp', false);

    $location = Location::create([
        'name' => 'AI Video Marina',
        'code' => 'AIV',
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
        'boat_name' => 'AI Ready Yacht',
        'manufacturer' => 'NauticSecure',
        'model' => '42 Fly',
        'status' => 'active',
        'price' => 450000,
        'location_city' => 'Amsterdam',
    ]);

    Storage::disk('public')->put('approved/master/ai-ready-yacht.jpg', 'image-binary');

    YachtImage::create([
        'yacht_id' => $yacht->id,
        'optimized_master_url' => 'approved/master/ai-ready-yacht.jpg',
        'status' => 'approved',
        'sort_order' => 1,
    ]);

    $video = Video::create([
        'yacht_id' => $yacht->id,
        'status' => 'queued',
        'template_type' => 'vertical_slideshow_v1',
        'generation_provider' => 'openai_sora',
    ]);

    $sourceVideoPath = tempnam(sys_get_temp_dir(), 'openai-video-test-');
    file_put_contents($sourceVideoPath, 'fake-mp4-binary');

    $openAiVideos = Mockery::mock(OpenAiVideoGenerationService::class);
    $openAiVideos->shouldReceive('providerName')->andReturn('openai_sora');
    $openAiVideos->shouldReceive('isConfigured')->andReturn(true);
    $openAiVideos->shouldReceive('submit')
        ->once()
        ->andReturn([
            'id' => 'video_123',
            'status' => 'completed',
            'progress' => 100,
            'seconds' => '8',
        ]);
    $openAiVideos->shouldReceive('status')->once()->andReturn('completed');
    $openAiVideos->shouldReceive('progress')->once()->andReturn(100);
    $openAiVideos->shouldReceive('isTerminalStatus')->once()->with('completed')->andReturn(true);
    $openAiVideos->shouldReceive('isFailureStatus')->once()->with('completed')->andReturn(false);
    $openAiVideos->shouldReceive('download')
        ->once()
        ->with('video_123', Mockery::type('string'))
        ->andReturnUsing(function (string $providerJobId, string $destinationPath) use ($sourceVideoPath): void {
            copy($sourceVideoPath, $destinationPath);
        });
    $openAiVideos->shouldReceive('durationSeconds')->once()->andReturn(8);

    $ffmpeg = Mockery::mock(FFmpegService::class);
    $ffmpeg->shouldReceive('isAvailable')->andReturn(false);

    try {
        (new RenderMarketingVideo($video->id))->handle(
            app(VideoAutomationService::class),
            $ffmpeg,
            $openAiVideos
        );
    } finally {
        @unlink($sourceVideoPath);
    }

    $video = $video->fresh();

    expect($video)->not->toBeNull();
    expect($video->status)->toBe('ready');
    expect($video->generation_provider)->toBe('openai_sora');
    expect($video->provider_job_id)->toBe('video_123');
    expect($video->provider_status)->toBe('completed');
    expect($video->provider_progress)->toBe(100);
    expect($video->duration_seconds)->toBe(8);
    expect($video->video_path)->not->toBeNull();
    expect($video->thumbnail_path)->toBeNull();
    expect($video->caption)->toContain('AI Ready Yacht');

    Storage::disk('public')->assertExists($video->video_path);
});

test('render marketing video uses the persisted source image ids when provided', function () {
    Storage::fake('public');
    Queue::fake();

    config()->set('video_automation.provider', 'openai_sora');
    config()->set('video_automation.auto_schedule', false);
    config()->set('video_automation.auto_notify_owner_whatsapp', false);

    $location = Location::create([
        'name' => 'AI Source Scoped Marina',
        'code' => 'ASM',
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
        'boat_name' => 'Scoped Source Yacht',
        'manufacturer' => 'NauticSecure',
        'model' => '48 Fly',
        'status' => 'active',
    ]);

    Storage::disk('public')->put('approved/master/source-first.jpg', 'first-image');
    Storage::disk('public')->put('approved/master/source-second.jpg', 'second-image');

    YachtImage::create([
        'yacht_id' => $yacht->id,
        'optimized_master_url' => 'approved/master/source-first.jpg',
        'status' => 'approved',
        'sort_order' => 1,
    ]);

    $selectedImage = YachtImage::create([
        'yacht_id' => $yacht->id,
        'optimized_master_url' => 'approved/master/source-second.jpg',
        'status' => 'approved',
        'sort_order' => 2,
    ]);

    $video = Video::create([
        'yacht_id' => $yacht->id,
        'status' => 'queued',
        'template_type' => 'vertical_slideshow_v1',
        'generation_provider' => 'openai_sora',
        'source_image_ids_json' => [$selectedImage->id],
    ]);

    $sourceVideoPath = tempnam(sys_get_temp_dir(), 'openai-video-scoped-test-');
    file_put_contents($sourceVideoPath, 'fake-mp4-binary');

    $openAiVideos = Mockery::mock(OpenAiVideoGenerationService::class);
    $openAiVideos->shouldReceive('providerName')->andReturn('openai_sora');
    $openAiVideos->shouldReceive('isConfigured')->andReturn(true);
    $openAiVideos->shouldReceive('submit')
        ->once()
        ->withArgs(function (Yacht $submittedYacht, array $imagePaths) use ($yacht) {
            $uniquePaths = array_values(array_unique($imagePaths));

            return $submittedYacht->is($yacht)
                && count($imagePaths) >= 1
                && count($uniquePaths) === 1
                && str_contains($uniquePaths[0], 'source-second.jpg');
        })
        ->andReturn([
            'id' => 'video_456',
            'status' => 'completed',
            'progress' => 100,
            'seconds' => '8',
        ]);
    $openAiVideos->shouldReceive('status')->once()->andReturn('completed');
    $openAiVideos->shouldReceive('progress')->once()->andReturn(100);
    $openAiVideos->shouldReceive('isTerminalStatus')->once()->with('completed')->andReturn(true);
    $openAiVideos->shouldReceive('isFailureStatus')->once()->with('completed')->andReturn(false);
    $openAiVideos->shouldReceive('download')
        ->once()
        ->with('video_456', Mockery::type('string'))
        ->andReturnUsing(function (string $providerJobId, string $destinationPath) use ($sourceVideoPath): void {
            copy($sourceVideoPath, $destinationPath);
        });
    $openAiVideos->shouldReceive('durationSeconds')->once()->andReturn(8);

    $ffmpeg = Mockery::mock(FFmpegService::class);
    $ffmpeg->shouldReceive('isAvailable')->andReturn(false);

    try {
        (new RenderMarketingVideo($video->id))->handle(
            app(VideoAutomationService::class),
            $ffmpeg,
            $openAiVideos
        );
    } finally {
        @unlink($sourceVideoPath);
    }

    expect($video->fresh()->status)->toBe('ready');
});
