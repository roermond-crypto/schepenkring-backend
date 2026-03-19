<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\Yacht;
use App\Services\FFmpegService;
use App\Services\OpenAiVideoGenerationService;
use App\Services\VideoAutomationService;
use App\Services\VideoCaptionService;
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
    public int $tries = 30;
    public array $backoff = [60, 180, 300, 600];

    private int $videoId;

    public function __construct(int $videoId)
    {
        $this->videoId = $videoId;
        $this->onQueue('video-rendering');
        $this->afterCommit();
    }

    public function handle(
        VideoAutomationService $automation,
        FFmpegService $ffmpeg,
        OpenAiVideoGenerationService $openAiVideos
    ): void {
        $startedAt = microtime(true);
        $workDir = null;
        $video = null;
        $yacht = null;

        $this->logInfo('started');

        $video = Video::find($this->videoId);
        if (! $video) {
            $this->logError('video_not_found');
            return;
        }

        $this->logInfo('video_loaded', [
            'status' => $video->status,
            'generation_provider' => $video->generation_provider,
            'yacht_id' => $video->yacht_id,
        ], $video);

        $yacht = $video->yacht;
        if (! $yacht) {
            $this->failJob($video, 'Yacht not found');
            return;
        }

        $this->logInfo('yacht_loaded', [
            'boat_name' => $yacht->boat_name,
            'location_city' => $yacht->location_city,
        ], $video, $yacht);

        $provider = (string) config('video_automation.provider', 'openai_sora');

        $this->logInfo('provider_selected', [
            'provider' => $provider,
        ], $video, $yacht);

        $video->update([
            'status' => 'processing',
            'generation_provider' => $provider,
            'error_message' => null,
        ]);

        $this->logInfo('video_marked_processing', [
            'status' => 'processing',
            'provider' => $provider,
        ], $video->fresh(), $yacht);

        try {
            if ($provider === 'ffmpeg') {
                if (! $ffmpeg->isAvailable()) {
                    $this->failJob($video, 'FFmpeg is not installed or not accessible');
                    return;
                }

                $this->logInfo('ffmpeg_available', [], $video, $yacht);
                $workDir = $this->ensureWorkDir($video, $yacht);
                $imagePaths = $this->collectImages($yacht, $automation);

                if ($imagePaths === []) {
                    $this->cleanup($workDir);
                    $this->failJob($video, 'No images found for this yacht');
                    return;
                }

                [$videoPath, $thumbnailPath, $duration] = $this->renderLocalSlideshow(
                    $video,
                    $yacht,
                    $ffmpeg,
                    $imagePaths,
                    $workDir
                );

                $this->finalizeGeneratedVideo(
                    $video,
                    $yacht,
                    $ffmpeg,
                    $videoPath,
                    $thumbnailPath,
                    $workDir,
                    $startedAt,
                    $duration,
                    [
                        'generation_provider' => 'ffmpeg',
                        'provider_job_id' => null,
                        'provider_status' => null,
                        'provider_progress' => null,
                        'provider_payload' => null,
                    ]
                );

                return;
            }

            if ($provider === $openAiVideos->providerName()) {
                $workDir = $this->ensureWorkDir($video, $yacht);
                $imagePaths = $this->collectImages($yacht, $automation);

                if ($imagePaths === []) {
                    $this->cleanup($workDir);
                    $this->failJob($video, 'No images found for this yacht');
                    return;
                }

                $this->handleOpenAiVideo(
                    $video,
                    $yacht,
                    $ffmpeg,
                    $openAiVideos,
                    $imagePaths,
                    $workDir,
                    $startedAt
                );

                return;
            }

            $this->failJob($video, 'Unsupported video automation provider: ' . $provider);
        } catch (\Throwable $e) {
            if ($workDir) {
                $this->cleanup($workDir);
            }

            $this->failJob($video, $e->getMessage());
            $this->logError('failed', [
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ], $video, $yacht);
        }
    }

    /**
     * @param  array<int, string>  $imagePaths
     */
    private function handleOpenAiVideo(
        Video $video,
        Yacht $yacht,
        FFmpegService $ffmpeg,
        OpenAiVideoGenerationService $openAiVideos,
        array $imagePaths,
        string $workDir,
        float $startedAt
    ): void {
        $isPolling = $video->provider_job_id !== null;

        if (! $openAiVideos->isConfigured()) {
            $this->cleanup($workDir);
            $this->failJob($video, 'OPENAI_API_KEY is not configured for AI video generation.');
            return;
        }

        if ($video->provider_job_id) {
            $this->logInfo('openai_poll_started', [
                'provider_job_id' => $video->provider_job_id,
            ], $video, $yacht);

            $providerPayload = $openAiVideos->retrieve($video->provider_job_id);
        } else {
            $this->logInfo('openai_submission_started', [
                'model' => config('video_automation.openai.model', 'sora-2'),
                'size' => config('video_automation.openai.size', '720x1280'),
                'seconds' => config('video_automation.openai.seconds', '8'),
                'image_count' => count($imagePaths),
            ], $video, $yacht);

            $providerPayload = $openAiVideos->submit($yacht, $imagePaths, $workDir);
        }

        $providerStatus = $openAiVideos->status($providerPayload);
        $providerProgress = $openAiVideos->progress($providerPayload);
        $providerJobId = (string) ($providerPayload['id'] ?? $video->provider_job_id ?? '');

        $video->update([
            'generation_provider' => $openAiVideos->providerName(),
            'provider_job_id' => $providerJobId !== '' ? $providerJobId : null,
            'provider_status' => $providerStatus,
            'provider_progress' => $providerProgress,
            'provider_payload' => $providerPayload,
        ]);

        $video = $video->fresh();

        $this->logInfo($isPolling ? 'openai_poll_finished' : 'openai_submission_finished', [
            'provider_job_id' => $providerJobId,
            'provider_status' => $providerStatus,
            'provider_progress' => $providerProgress,
        ], $video, $yacht);

        if ($providerJobId === '') {
            throw new \RuntimeException('OpenAI video generation did not return a provider job id.');
        }

        if (! $openAiVideos->isTerminalStatus($providerStatus)) {
            $this->cleanup($workDir);
            $this->releaseForPolling($video, $yacht, $openAiVideos->pollDelaySeconds(), [
                'provider_job_id' => $providerJobId,
                'provider_status' => $providerStatus,
                'provider_progress' => $providerProgress,
            ]);
            return;
        }

        if ($openAiVideos->isFailureStatus($providerStatus)) {
            $this->cleanup($workDir);
            $this->failJob($video, $openAiVideos->errorMessage($providerPayload));
            return;
        }

        $downloadPath = $workDir . '/openai-video.mp4';

        $this->logInfo('openai_download_started', [
            'provider_job_id' => $providerJobId,
            'destination_path' => $downloadPath,
        ], $video, $yacht);

        $openAiVideos->download($providerJobId, $downloadPath);

        $this->logInfo('openai_download_finished', [
            'destination_path' => $downloadPath,
            'exists' => file_exists($downloadPath),
            'bytes' => file_exists($downloadPath) ? filesize($downloadPath) : null,
        ], $video, $yacht);

        $thumbnailPath = $this->maybeCreateThumbnail($video, $yacht, $ffmpeg, $downloadPath, $workDir);
        $duration = $openAiVideos->durationSeconds($providerPayload)
            ?? $this->safeDurationFromFile($ffmpeg, $downloadPath);

        $this->finalizeGeneratedVideo(
            $video,
            $yacht,
            $ffmpeg,
            $downloadPath,
            $thumbnailPath,
            $workDir,
            $startedAt,
            $duration,
            [
                'generation_provider' => $openAiVideos->providerName(),
                'provider_job_id' => $providerJobId,
                'provider_status' => $providerStatus,
                'provider_progress' => $providerProgress,
                'provider_payload' => $providerPayload,
            ]
        );
    }

    /**
     * @param  array<int, string>  $imagePaths
     * @return array{0:string,1:string,2:?int}
     */
    private function renderLocalSlideshow(
        Video $video,
        Yacht $yacht,
        FFmpegService $ffmpeg,
        array $imagePaths,
        string $workDir
    ): array {
        $slideshowPath = $workDir . '/slideshow.mp4';
        $thumbnailPath = $workDir . '/thumb.jpg';
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
            'thumbnail_path' => $thumbnailPath,
        ], $video, $yacht);

        $ffmpeg->createThumbnail($slideshowPath, $thumbnailPath, 1);

        $this->logInfo('thumbnail_finished', [
            'thumbnail_path' => $thumbnailPath,
            'exists' => file_exists($thumbnailPath),
            'bytes' => file_exists($thumbnailPath) ? filesize($thumbnailPath) : null,
        ], $video, $yacht);

        return [
            $slideshowPath,
            $thumbnailPath,
            $ffmpeg->getDuration($slideshowPath) ?: null,
        ];
    }

    private function finalizeGeneratedVideo(
        Video $video,
        Yacht $yacht,
        FFmpegService $ffmpeg,
        string $videoSourcePath,
        ?string $thumbnailSourcePath,
        string $workDir,
        float $startedAt,
        ?int $duration,
        array $extraUpdate = []
    ): void {
        $timestamp = time();
        $storagePath = "videos/marketing/yacht-{$yacht->id}-{$timestamp}.mp4";
        $thumbnailStoragePath = $thumbnailSourcePath
            ? "videos/marketing/yacht-{$yacht->id}-{$timestamp}-thumb.jpg"
            : null;

        $this->logInfo('storage_started', [
            'source_video_path' => $videoSourcePath,
            'source_thumbnail_path' => $thumbnailSourcePath,
            'video_storage_path' => $storagePath,
            'thumbnail_storage_path' => $thumbnailStoragePath,
        ], $video, $yacht);

        $this->storePublicFile($storagePath, $videoSourcePath);

        if ($thumbnailStoragePath && $thumbnailSourcePath && file_exists($thumbnailSourcePath)) {
            $this->storePublicFile($thumbnailStoragePath, $thumbnailSourcePath);
        } else {
            $thumbnailStoragePath = null;
        }

        $storageFullPath = Storage::disk('public')->path($storagePath);
        $resolvedDuration = $duration ?? $this->safeDurationFromFile($ffmpeg, $storageFullPath);
        $fileSize = filesize($storageFullPath) ?: null;
        $checksum = hash_file('sha256', $storageFullPath);

        $this->logInfo('storage_finished', [
            'video_storage_path' => $storagePath,
            'thumbnail_storage_path' => $thumbnailStoragePath,
            'duration_seconds' => $resolvedDuration,
            'file_size_bytes' => $fileSize,
            'checksum' => $checksum,
        ], $video, $yacht);

        $caption = app(VideoCaptionService::class)->buildCaption($yacht);

        $this->logInfo('caption_built', [
            'caption_length' => mb_strlen($caption),
        ], $video, $yacht);

        $video->update(array_merge([
            'status' => 'ready',
            'video_path' => $storagePath,
            'video_url' => Storage::disk('public')->url($storagePath),
            'thumbnail_path' => $thumbnailStoragePath,
            'thumbnail_url' => $thumbnailStoragePath ? Storage::disk('public')->url($thumbnailStoragePath) : null,
            'duration_seconds' => $resolvedDuration,
            'file_size_bytes' => $fileSize,
            'checksum' => $checksum,
            'caption' => $caption,
            'error_message' => null,
            'generated_at' => now(),
        ], $extraUpdate));

        $video = $video->fresh();

        $this->logInfo('video_marked_ready', [
            'video_path' => $storagePath,
            'thumbnail_path' => $thumbnailStoragePath,
        ], $video, $yacht);

        $this->cleanup($workDir);
        $this->runPostReadyActions($video, $yacht, $startedAt);
    }

    private function runPostReadyActions(Video $video, Yacht $yacht, float $startedAt): void
    {
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
    }

    /**
     * @param  array<int, string>  $paths
     */
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

    private function ensureWorkDir(Video $video, Yacht $yacht): string
    {
        $workDir = sys_get_temp_dir() . '/marketing_video_' . $video->id;

        if (! is_dir($workDir)) {
            mkdir($workDir, 0777, true);
            $this->logInfo('workdir_created', ['work_dir' => $workDir], $video, $yacht);
        } else {
            $this->logInfo('workdir_exists', ['work_dir' => $workDir], $video, $yacht);
        }

        return $workDir;
    }

    private function maybeCreateThumbnail(
        Video $video,
        Yacht $yacht,
        FFmpegService $ffmpeg,
        string $videoPath,
        string $workDir
    ): ?string {
        if (! $ffmpeg->isAvailable()) {
            $this->logWarning('thumbnail_skipped_ffmpeg_missing', [
                'source_path' => $videoPath,
            ], $video, $yacht);

            return null;
        }

        $thumbnailPath = $workDir . '/thumb.jpg';

        $this->logInfo('thumbnail_started', [
            'source_path' => $videoPath,
            'thumbnail_path' => $thumbnailPath,
        ], $video, $yacht);

        try {
            $ffmpeg->createThumbnail($videoPath, $thumbnailPath, 1);
        } catch (\Throwable $e) {
            $this->logWarning('thumbnail_failed', [
                'source_path' => $videoPath,
                'thumbnail_path' => $thumbnailPath,
                'error' => $e->getMessage(),
            ], $video, $yacht);

            return null;
        }

        $this->logInfo('thumbnail_finished', [
            'thumbnail_path' => $thumbnailPath,
            'exists' => file_exists($thumbnailPath),
            'bytes' => file_exists($thumbnailPath) ? filesize($thumbnailPath) : null,
        ], $video, $yacht);

        return $thumbnailPath;
    }

    private function safeDurationFromFile(FFmpegService $ffmpeg, string $videoPath): ?int
    {
        if (! $ffmpeg->isAvailable()) {
            return null;
        }

        $duration = $ffmpeg->getDuration($videoPath);

        return $duration > 0 ? $duration : null;
    }

    private function storePublicFile(string $storagePath, string $sourcePath): void
    {
        $stream = fopen($sourcePath, 'rb');

        if (! $stream) {
            throw new \RuntimeException('Unable to open generated file for storage: ' . $sourcePath);
        }

        try {
            Storage::disk('public')->put($storagePath, $stream);
        } finally {
            fclose($stream);
        }
    }

    private function releaseForPolling(Video $video, Yacht $yacht, int $delaySeconds, array $context = []): void
    {
        $this->logInfo('requeued_for_polling', array_merge([
            'delay_seconds' => $delaySeconds,
        ], $context), $video, $yacht);

        $this->release($delaySeconds);
    }

    private function cleanup(string $workDir): void
    {
        if (! is_dir($workDir)) {
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

    private function logWarning(string $step, array $context = [], ?Video $video = null, ?Yacht $yacht = null): void
    {
        Log::warning("[RenderMarketingVideo] {$step}", $this->logContext($context, $video, $yacht));
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
            'generation_provider' => $video?->generation_provider ?? config('video_automation.provider', 'openai_sora'),
            'provider_job_id' => $video?->provider_job_id,
            'yacht_id' => $yacht?->id ?? $video?->yacht_id,
            'queue' => 'video-rendering',
        ], $context), static fn ($value) => $value !== null);
    }
}
