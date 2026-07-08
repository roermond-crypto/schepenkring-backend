<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\VideoPlan;
use App\Services\FFmpegService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RenderFromPlan implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout   = 660;
    public int $tries     = 1;
    public int $uniqueFor = 700;

    public function uniqueId(): string
    {
        return (string) $this->planId;
    }

    public function __construct(private int $planId)
    {
        $this->onQueue('video-rendering');
    }

    public function handle(): void
    {
        $plan = VideoPlan::with('yacht.images', 'yacht.location', 'template')->find($this->planId);
        if (!$plan) {
            Log::error("VideoPlan {$this->planId} not found");
            return;
        }

        $ffmpeg = new FFmpegService();
        if (!$ffmpeg->isAvailable()) {
            $this->fail($plan, 'FFmpeg not available');
            return;
        }

        $plan->update(['status' => 'rendering', 'render_started_at' => now()]);

        $uniqueId = uniqid();
        $tempDir  = "plan_render_{$uniqueId}";
        Storage::disk('local')->makeDirectory($tempDir);
        $tempBase = Storage::disk('local')->path($tempDir);

        try {
            $finalPlan = $plan->final_plan_json;
            $scenes    = $finalPlan['scenes'] ?? [];
            $intro     = $finalPlan['intro']  ?? [];
            $outro     = $finalPlan['outro']  ?? [];
            $audio     = $finalPlan['audio']  ?? [];
            $settings  = $plan->template->settings_json ?? [];

            $imageMap = $plan->yacht->images->keyBy('id');

            $imagePaths   = [];
            $durations    = [];
            $overlays     = [];

            foreach ($scenes as $scene) {
                if (($scene['importance'] ?? '') === 'weak') continue;
                if (!empty($scene['exclude'])) continue;

                $imageId  = $scene['image_id'] ?? null;
                $img      = $imageMap[$imageId] ?? null;
                if (!$img) continue;

                $localPath = $this->resolveImagePath($img, $tempBase);
                if (!$localPath) continue;

                $imagePaths[] = $localPath;
                $durations[]  = (float) ($scene['duration'] ?? 3.0);
                $overlays[]   = ($scene['overlay']['enabled'] ?? false) && !empty($scene['overlay']['headline'])
                    ? $scene['overlay']['headline']
                    : '';
            }

            if (empty($imagePaths)) {
                $this->fail($plan, 'No resolvable images found for this plan');
                return;
            }

            $yachtTitle    = trim((string) ($plan->yacht->boat_name ?: ($plan->yacht->manufacturer . ' ' . $plan->yacht->model)));
            $yachtSubtitle = implode(' • ', array_filter([
                $plan->yacht->year,
                $plan->yacht->length_overall ? $plan->yacht->length_overall . 'm' : null,
                $plan->yacht->location_city,
            ]));

            $ctaText    = $settings['cta_text'] ?? config('video_automation.cta_text', 'View full specs on Schepenkring.nl');
            $ctaSub     = parse_url(config('app.url'), PHP_URL_HOST) ?: 'schepenkring.nl';
            $introTitle = $intro['title'] ?? $intro['text'] ?? $yachtTitle;
            $introSub   = $intro['subtitle'] ?? $yachtSubtitle;

            $resolution = match ($plan->variation) {
                'vertical' => '1080:1920',
                'square'   => '1080:1080',
                default    => '1920:1080',
            };

            $rawPath = "{$tempBase}/raw.mp4";
            $location = $plan->yacht->location;
            $locationIntroPath = $location?->video_intro_media
                ? $this->resolveLocationMediaPath($location->video_intro_media, $tempBase, "loc_intro_{$location->id}")
                : null;
            $locationOutroPath = $location?->video_outro_media
                ? $this->resolveLocationMediaPath($location->video_outro_media, $tempBase, "loc_outro_{$location->id}")
                : null;

            if ($plan->variation === 'teaser') {
                $ffmpeg->renderTeaserClip($imagePaths, $rawPath, 5, 2.5, $resolution);
            } else {
                $ffmpeg->renderFullVideo(
                    $imagePaths, $rawPath, $durations, $overlays,
                    $introTitle, $introSub, $ctaText, $ctaSub,
                    'fade', (float) ($settings['default_transition_duration'] ?? 0.8),
                    $resolution,
                    $locationIntroPath,
                    $locationOutroPath
                );
            }

            // Audio
            $finalPath = "{$tempBase}/final.mp4";
            $musicPath = $this->getMusicPath($audio['music_profile'] ?? $settings['music_family'] ?? null);
            if ($musicPath) {
                $ffmpeg->addBackgroundMusicWithFade($rawPath, $musicPath, $finalPath);
            } else {
                $finalPath = $rawPath;
            }

            // Watermark
            $logoPath = public_path('logos/schepen-kring.svg');
            if (file_exists($logoPath)) {
                $watermarked = "{$tempBase}/watermarked.mp4";
                $ffmpeg->addWatermark($finalPath, $logoPath, $watermarked);
                $finalPath = $watermarked;
            }

            // Save
            $storagePath = "videos/plans/plan-{$plan->id}-" . time() . '.mp4';
            $fullStorage = Storage::disk('public')->path($storagePath);
            $dir = dirname($fullStorage);
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            copy($finalPath, $fullStorage);

            $outputUrl = rtrim(config('app.url'), '/') . '/storage/' . $storagePath;

            $plan->update([
                'status'              => 'rendered',
                'render_output_url'   => $outputUrl,
                'render_completed_at' => now(),
                // Clear any previous failure output so the UI doesn't show stale errors.
                'validation_errors'   => [],
            ]);

            // Create Video record for Social Dashboard pipeline
            if ($plan->yacht_id) {
                $duration = $ffmpeg->getDuration($fullStorage);
                $video = \App\Models\Video::create([
                    'yacht_id'         => $plan->yacht_id,
                    'status'           => 'ready',
                    'template_type'    => 'ai_plan',
                    'video_path'       => $storagePath,
                    'video_url'        => $outputUrl,
                    'duration_seconds' => $duration,
                    'file_size_bytes'  => filesize($fullStorage),
                    'generated_at'     => now(),
                ]);

                // Auto-schedule via Yext if enabled
                if (config('video_automation.auto_schedule')) {
                    app(\App\Services\VideoSchedulerService::class)->scheduleNextAvailable(
                        $video,
                        config('video_automation.schedule_time', '10:30'),
                        (bool) config('video_automation.skip_weekends', false),
                        config('video_automation.default_publishers', []),
                        config('services.yext.account_id'),
                        config('services.yext.entity_id')
                    );
                }

                AuditLog::create([
                    'action' => 'video.generated',
                    'category' => 'video',
                    'risk_level' => 'low',
                    'result' => 'success',
                    'entity_type' => 'yacht',
                    'entity_id' => $plan->yacht_id,
                    'meta' => [
                        'video_plan_id' => $plan->id,
                        'video_id' => $video->id,
                        'video_url' => $outputUrl,
                        'template_type' => 'ai_plan',
                        'variation' => $plan->variation,
                    ],
                ]);
            }

            Log::info("VideoPlan {$plan->id} rendered: {$outputUrl}");
        } catch (\Throwable $e) {
            $this->fail($plan, $e);
        } finally {
            Storage::disk('local')->deleteDirectory($tempDir);
        }
    }

    private function resolveImagePath(\App\Models\YachtImage $img, string $tempBase): ?string
    {
        foreach (array_filter([$img->url, $img->optimized_master_url]) as $relativePath) {
            $diskPath = Storage::disk('public')->path($relativePath);
            if (file_exists($diskPath)) {
                return $diskPath;
            }
        }

        $fallbackUrl = rtrim(config('app.url'), '/') . '/storage/' . ltrim($img->url, '/');
        $candidates  = array_filter([
            filter_var($fallbackUrl, FILTER_VALIDATE_URL) ? $fallbackUrl : null,
        ]);

        $localPath = "{$tempBase}/img_{$img->id}.jpg";
        foreach ($candidates as $url) {
            try {
                $response = Http::timeout(10)->get($url);
                if ($response->successful()) {
                    file_put_contents($localPath, $response->body());
                    return $localPath;
                }
            } catch (\Throwable) {}
        }

        Log::warning("RenderFromPlan: could not resolve image #{$img->id} (url: {$img->url})");
        return null;
    }

    /**
     * Resolve a location's stored intro/outro media (a relative storage path
     * or a full URL, however it was saved by the admin upload) to a local
     * file FFmpeg can read, preserving its original extension so
     * FFmpegService can tell image from video.
     */
    private function resolveLocationMediaPath(string $stored, string $tempBase, string $filenameStem): ?string
    {
        $extension = pathinfo(parse_url($stored, PHP_URL_PATH) ?: $stored, PATHINFO_EXTENSION) ?: 'jpg';

        $diskPath = Storage::disk('public')->path($stored);
        if (file_exists($diskPath)) {
            return $diskPath;
        }

        $url = filter_var($stored, FILTER_VALIDATE_URL)
            ? $stored
            : rtrim(config('app.url'), '/') . '/storage/' . ltrim($stored, '/');

        $localPath = "{$tempBase}/{$filenameStem}.{$extension}";
        try {
            $response = Http::timeout(15)->get($url);
            if ($response->successful()) {
                file_put_contents($localPath, $response->body());
                return $localPath;
            }
        } catch (\Throwable) {}

        Log::warning("RenderFromPlan: could not resolve location media ({$stored})");
        return null;
    }

    private function getMusicPath(?string $profile): ?string
    {
        if (!$profile) return null;
        $path = resource_path("music/{$profile}.mp3");
        return file_exists($path) ? $path : null;
    }

    /**
     * Persist a render failure in a way that is readable for non-technical users,
     * while still keeping actionable technical details for debugging.
     */
    private function fail(VideoPlan $plan, \Throwable|string $exception): void
    {
        $message = $exception instanceof \Throwable ? $exception->getMessage() : $exception;
        $details = $this->formatFailureDetails($exception);
        $plan->update([
            'status' => 'failed',
            'validation_errors' => $details,
        ]);

        AuditLog::create([
            'action' => 'video.generation_failed',
            'category' => 'video',
            'risk_level' => 'medium',
            'result' => 'fail',
            'entity_type' => 'yacht',
            'entity_id' => $plan->yacht_id,
            'meta' => [
                'video_plan_id' => $plan->id,
                'reason' => $message,
            ],
        ]);

        Log::error("VideoPlan {$plan->id} failed: {$message}");
    }

    public function failed(\Throwable $exception): void
    {
        $plan = VideoPlan::find($this->planId);
        if ($plan) {
            // If we already produced an output URL, do not overwrite success with a failure state.
            // This can happen if the worker is stopped (SIGTERM) after the file is written.
            if (!empty($plan->render_output_url) || $plan->status === 'rendered') {
                $plan->update([
                    'status' => 'rendered',
                    'validation_errors' => [],
                ]);
                Log::warning("VideoPlan {$this->planId} job marked failed after output existed; preserving rendered state.");
                return;
            }

            $plan->update([
                'status' => 'failed',
                'validation_errors' => $this->formatFailureDetails($exception),
            ]);
        }
        Log::error("VideoPlan {$this->planId} job failed: {$exception->getMessage()}");
    }

    /**
     * @return array<int, string>
     */
    private function formatFailureDetails(\Throwable|string $exception): array
    {
        $raw = trim($exception instanceof \Throwable ? $exception->getMessage() : $exception);
        $rawLower = strtolower($raw);

        $friendly = 'The video could not be rendered. Please try again.';
        if (str_contains($rawLower, 'ffmpeg')) {
            $friendly = 'The video builder (FFmpeg) failed while combining the clips. Try again, and if it keeps failing, contact support.';
        } elseif (str_contains($rawLower, 'no resolvable images')) {
            $friendly = 'No usable photos were found for this video plan. Please check the yacht images and try again.';
        } elseif (str_contains($rawLower, 'ffmpeg not available')) {
            $friendly = 'Video rendering is temporarily unavailable on the server. Please try again later.';
        } elseif (str_contains($rawLower, 'permission denied')) {
            $friendly = 'The server cannot access a required file. Please try again or contact support.';
        }

        $technical = $raw !== '' ? $this->truncateForUi($raw, 2000) : 'No additional details available.';

        return [
            $friendly,
            "Technical details:\n{$technical}",
        ];
    }

    private function truncateForUi(string $value, int $max): string
    {
        if ($max < 10) return substr($value, 0, max(0, $max));
        if (strlen($value) <= $max) return $value;
        return substr($value, 0, $max - 1) . '…';
    }
}
