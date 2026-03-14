<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\Yacht;
use App\Jobs\SendBoatVideoWhatsappJob;
use App\Services\FFmpegService;
use App\Services\VideoCaptionService;
use App\Services\VideoAutomationService;
use App\Services\VideoSchedulerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RenderMarketingVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public array $backoff = [60, 180, 300];

    private int $videoId;

    public function __construct(int $videoId)
    {
        $this->videoId = $videoId;
        $this->onQueue('video-rendering');
        $this->afterCommit();
    }

    public function handle(VideoAutomationService $automation): void
    {
        // Stub implementation to keep the API contract intact.
        Log::info('RenderMarketingVideo stub executed', ['video_id' => $this->videoId]);
        $video = Video::find($this->videoId);
        if (!$video) {
            Log::error("Marketing video {$this->videoId} not found");
            return;
        }

        $yacht = $video->yacht;
        if (!$yacht) {
            $this->failJob($video, 'Yacht not found');
            return;
        }

        $ffmpeg = new FFmpegService();
        if (!$ffmpeg->isAvailable()) {
            $this->failJob($video, 'FFmpeg is not installed or not accessible');
            return;
        }

        $video->update(['status' => 'processing']);

        try {
            $imagePaths = $this->collectImages($yacht, $automation);
            if (empty($imagePaths)) {
                $this->failJob($video, 'No images found for this yacht');
                return;
            }

            $workDir = sys_get_temp_dir() . '/marketing_video_' . $video->id;
            if (!is_dir($workDir)) {
                mkdir($workDir, 0777, true);
            }

            $slideshowPath = "{$workDir}/slideshow.mp4";
            $thumbPath = "{$workDir}/thumb.jpg";

            $overlayLines = $this->buildOverlayLines($yacht);
            $secondsPerImage = (int) config('video_automation.seconds_per_image', 2);

            $ffmpeg->renderVerticalSlideshow($imagePaths, $slideshowPath, $secondsPerImage, $overlayLines);
            $ffmpeg->createThumbnail($slideshowPath, $thumbPath, 1);

            $storagePath = "videos/marketing/yacht-{$yacht->id}-" . time() . '.mp4';
            $thumbStoragePath = "videos/marketing/yacht-{$yacht->id}-" . time() . '-thumb.jpg';

            Storage::disk('public')->put($storagePath, file_get_contents($slideshowPath));
            Storage::disk('public')->put($thumbStoragePath, file_get_contents($thumbPath));

            $storageFullPath = Storage::disk('public')->path($storagePath);
            $duration = $ffmpeg->getDuration($storageFullPath);
            $fileSize = filesize($storageFullPath) ?: null;
            $checksum = hash_file('sha256', $storageFullPath);

            $caption = app(VideoCaptionService::class)->buildCaption($yacht);

            $video->update([
                'status' => 'ready',
                'video_path' => $storagePath,
                'video_url' => Storage::disk('public')->url($storagePath),
                'thumbnail_path' => $thumbStoragePath,
                'thumbnail_url' => Storage::disk('public')->url($thumbStoragePath),
                'duration_seconds' => $duration,
                'file_size_bytes' => $fileSize,
                'checksum' => $checksum,
                'caption' => $caption,
                'generated_at' => now(),
            ]);

            $this->cleanup($workDir);

            if ($this->shouldAutoSchedule($yacht)) {
                $scheduler = app(VideoSchedulerService::class);
                $scheduler->scheduleNextAvailable(
                    $video,
                    config('video_automation.schedule_time', '10:30'),
                    (bool) config('video_automation.skip_weekends', false),
                    $this->publishersFor($yacht),
                    config('services.yext.account_id'),
                    config('services.yext.entity_id')
                );
            }

            if (config('video_automation.auto_notify_owner_whatsapp', true)) {
                SendBoatVideoWhatsappJob::dispatch($video->id)->onQueue('whatsapp');
            }

            Log::info('Marketing video rendered', ['video_id' => $video->id, 'yacht_id' => $yacht->id]);
        } catch (\Throwable $e) {
            $this->failJob($video, $e->getMessage());
            Log::error("Marketing video failed for yacht {$yacht->id}: {$e->getMessage()}");
        }
    }

    private function collectImages(Yacht $yacht, VideoAutomationService $automation): array
    {
        $paths = $automation->collectRenderableImagePaths($yacht);

        $max = (int) config('video_automation.max_images', 15);
        $min = (int) config('video_automation.min_images', 8);
        $paths = array_slice($paths, 0, max($max, 1));

        if (count($paths) < $min && count($paths) > 0) {
            $idx = 0;
            while (count($paths) < $min) {
                $paths[] = $paths[$idx % count($paths)];
                $idx++;
            }
        }

        return $paths;
    }

    private function buildOverlayLines(Yacht $yacht): array
    {
        $lines = [];
        $name = trim((string) $yacht->boat_name);
        if ($name !== '') {
            $lines[] = $name;
        }

        $price = $yacht->price ?? $yacht->sale_price;
        if ($price !== null) {
            $lines[] = '€' . number_format((float) $price, 0, '.', ',');
        }

        if ($yacht->location_city) {
            $lines[] = $yacht->location_city;
        }

        $lines[] = config('video_automation.cta_text');

        return $lines;
    }

    private function cleanup(string $workDir): void
    {
        if (!is_dir($workDir)) {
            return;
        }
        foreach (glob("{$workDir}/*") as $file) {
            @unlink($file);
        }
        @rmdir($workDir);
    }

    private function failJob(Video $video, string $error): void
    {
        $video->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    private function shouldAutoSchedule(Yacht $yacht): bool
    {
        $settings = $yacht->videoSetting()->first();

        if ($settings) {
            return (bool) $settings->auto_publish_social;
        }

        return (bool) config('video_automation.auto_schedule');
    }

    /**
     * @return array<int, string>
     */
    private function publishersFor(Yacht $yacht): array
    {
        $settings = $yacht->videoSetting()->first();
        $publishers = $settings?->platforms;

        if (is_array($publishers) && $publishers !== []) {
            return $publishers;
        }

        return config('video_automation.default_publishers', []);
    }
}
