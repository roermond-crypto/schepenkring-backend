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
        $startedAt = microtime(true);

        $this->logInfo('started');
        $video = Video::find($this->videoId);
        if (!$video) {
            $this->logError('video_not_found');
            return;
        }

        $this->logInfo('video_loaded', [
            'status' => $video->status,
            'yacht_id' => $video->yacht_id,
        ], $video);

        $yacht = $video->yacht;
        if (!$yacht) {
            $this->failJob($video, 'Yacht not found');
            return;
        }

        $this->logInfo('yacht_loaded', [
            'boat_name' => $yacht->boat_name,
            'location_city' => $yacht->location_city,
        ], $video, $yacht);

        $ffmpeg = new FFmpegService();
        if (!$ffmpeg->isAvailable()) {
            $this->failJob($video, 'FFmpeg is not installed or not accessible');
            return;
        }

        $this->logInfo('ffmpeg_available', [], $video, $yacht);
        $video->update(['status' => 'processing']);
        $this->logInfo('video_marked_processing', ['status' => 'processing'], $video, $yacht);

        try {
            $imagePaths = $this->collectImages($yacht, $automation);
            if (empty($imagePaths)) {
                $this->failJob($video, 'No images found for this yacht');
                return;
            }

            $workDir = sys_get_temp_dir() . '/marketing_video_' . $video->id;
            if (!is_dir($workDir)) {
                mkdir($workDir, 0777, true);
                $this->logInfo('workdir_created', ['work_dir' => $workDir], $video, $yacht);
            } else {
                $this->logInfo('workdir_exists', ['work_dir' => $workDir], $video, $yacht);
            }

            $slideshowPath = "{$workDir}/slideshow.mp4";
            $thumbPath = "{$workDir}/thumb.jpg";

            $overlayLines = $this->buildOverlayLines($yacht);
            $secondsPerImage = (int) config('video_automation.seconds_per_image', 2);

            $this->logInfo('render_slideshow_started', [
                'image_count' => count($imagePaths),
                'seconds_per_image' => $secondsPerImage,
                'overlay_lines' => $overlayLines,
                'output_path' => $slideshowPath,
            ], $video, $yacht);
            $ffmpeg->renderVerticalSlideshow($imagePaths, $slideshowPath, $secondsPerImage, $overlayLines);

            $this->logInfo('render_slideshow_finished', [
                'output_path' => $slideshowPath,
                'exists' => file_exists($slideshowPath),
                'bytes' => file_exists($slideshowPath) ? filesize($slideshowPath) : null,
            ], $video, $yacht);

            $this->logInfo('thumbnail_started', [
                'source_path' => $slideshowPath,
                'thumbnail_path' => $thumbPath,
            ], $video, $yacht);
            $ffmpeg->createThumbnail($slideshowPath, $thumbPath, 1);

            $this->logInfo('thumbnail_finished', [
                'thumbnail_path' => $thumbPath,
                'exists' => file_exists($thumbPath),
                'bytes' => file_exists($thumbPath) ? filesize($thumbPath) : null,
            ], $video, $yacht);

            $storagePath = "videos/marketing/yacht-{$yacht->id}-" . time() . '.mp4';
            $thumbStoragePath = "videos/marketing/yacht-{$yacht->id}-" . time() . '-thumb.jpg';

            $this->logInfo('storage_started', [
                'video_storage_path' => $storagePath,
                'thumbnail_storage_path' => $thumbStoragePath,
            ], $video, $yacht);
            Storage::disk('public')->put($storagePath, file_get_contents($slideshowPath));
            Storage::disk('public')->put($thumbStoragePath, file_get_contents($thumbPath));

            $storageFullPath = Storage::disk('public')->path($storagePath);
            $duration = $ffmpeg->getDuration($storageFullPath);
            $fileSize = filesize($storageFullPath) ?: null;
            $checksum = hash_file('sha256', $storageFullPath);

            $this->logInfo('storage_finished', [
                'video_storage_path' => $storagePath,
                'thumbnail_storage_path' => $thumbStoragePath,
                'duration_seconds' => $duration,
                'file_size_bytes' => $fileSize,
                'checksum' => $checksum,
            ], $video, $yacht);

            $caption = app(VideoCaptionService::class)->buildCaption($yacht);
            $this->logInfo('caption_built', [
                'caption_length' => mb_strlen($caption),
            ], $video, $yacht);

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

            $this->logInfo('video_marked_ready', [
                'video_path' => $storagePath,
                'thumbnail_path' => $thumbStoragePath,
            ], $video->fresh(), $yacht);

            $this->cleanup($workDir);

            $shouldAutoSchedule = $this->shouldAutoSchedule($yacht);
            $this->logInfo('auto_schedule_evaluated', [
                'should_auto_schedule' => $shouldAutoSchedule,
            ], $video, $yacht);

            if ($shouldAutoSchedule) {
                $scheduler = app(VideoSchedulerService::class);
                $publishers = $this->publishersFor($yacht);

                $this->logInfo('auto_schedule_started', [
                    'publishers' => $publishers,
                ], $video, $yacht);
                $scheduler->scheduleNextAvailable(
                    $video,
                    config('video_automation.schedule_time', '10:30'),
                    (bool) config('video_automation.skip_weekends', false),
                    $publishers,
                    config('services.yext.account_id'),
                    config('services.yext.entity_id')
                );
                $this->logInfo('auto_schedule_finished', [
                    'publishers' => $publishers,
                ], $video, $yacht);
            }

            $shouldNotifyWhatsapp = (bool) config('video_automation.auto_notify_owner_whatsapp', true);
            $this->logInfo('whatsapp_notify_evaluated', [
                'should_notify_whatsapp' => $shouldNotifyWhatsapp,
            ], $video, $yacht);

            if ($shouldNotifyWhatsapp) {
                SendBoatVideoWhatsappJob::dispatch($video->id)->onQueue('whatsapp');
                $this->logInfo('whatsapp_notification_queued', [
                    'queue' => 'whatsapp',
                ], $video, $yacht);
            }

            $this->logInfo('completed', [
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
            ], $video, $yacht);
        } catch (\Throwable $e) {
            $this->failJob($video, $e->getMessage());
            $this->logError('failed', [
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ], $video, $yacht);
        }
    }

    private function collectImages(Yacht $yacht, VideoAutomationService $automation): array
    {
        $paths = $automation->collectRenderableImagePaths($yacht);
        $rawCount = count($paths);

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

        $this->logInfo('images_collected', [
            'raw_count' => $rawCount,
            'selected_count' => count($paths),
            'max_images' => $max,
            'min_images' => $min,
            'sample_paths' => array_slice($paths, 0, 3),
        ], null, $yacht);

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
            $this->logInfo('cleanup_skipped', ['work_dir' => $workDir, 'reason' => 'missing_directory']);
            return;
        }

        $files = glob("{$workDir}/*") ?: [];
        $this->logInfo('cleanup_started', [
            'work_dir' => $workDir,
            'file_count' => count($files),
        ]);

        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($workDir);

        $this->logInfo('cleanup_finished', [
            'work_dir' => $workDir,
        ]);
    }

    private function failJob(Video $video, string $error): void
    {
        $video->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);

        $this->logError('marked_failed', [
            'error' => $error,
        ], $video, $video->yacht);
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

    private function logInfo(string $step, array $context = [], ?Video $video = null, ?Yacht $yacht = null): void
    {
        Log::info("[RenderMarketingVideo] {$step}", $this->logContext($context, $video, $yacht));
    }

    private function logError(string $step, array $context = [], ?Video $video = null, ?Yacht $yacht = null): void
    {
        Log::error("[RenderMarketingVideo] {$step}", $this->logContext($context, $video, $yacht));
    }

    private function logContext(array $context = [], ?Video $video = null, ?Yacht $yacht = null): array
    {
        $jobId = $this->job && method_exists($this->job, 'getJobId')
            ? $this->job->getJobId()
            : null;

        return array_filter(array_merge([
            'job_id' => $jobId,
            'attempt' => $this->attempts(),
            'video_id' => $video?->id ?? $this->videoId,
            'video_status' => $video?->status,
            'yacht_id' => $yacht?->id ?? $video?->yacht_id,
            'queue' => 'video-rendering',
        ], $context), static fn ($value) => $value !== null);
    }
}
