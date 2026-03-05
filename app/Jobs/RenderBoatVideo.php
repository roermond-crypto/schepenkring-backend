<?php

namespace App\Jobs;

use App\Models\VideoJob;
use App\Models\Yacht;
use App\Services\FFmpegService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RenderBoatVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The queue this job should run on.
     */
    public string $queue = 'video-rendering';

    private int $videoJobId;

    public function __construct(int $videoJobId)
    {
        $this->videoJobId = $videoJobId;
        $this->onQueue('video-rendering');
    }

    public function handle(): void
    {
        $videoJob = VideoJob::find($this->videoJobId);
        if (!$videoJob) {
            Log::error("VideoJob {$this->videoJobId} not found");
            return;
        }

        $yacht = $videoJob->yacht;
        if (!$yacht) {
            $this->failJob($videoJob, 'Yacht not found');
            return;
        }

        $ffmpeg = new FFmpegService();

        // Check FFmpeg is available
        if (!$ffmpeg->isAvailable()) {
            $this->failJob($videoJob, 'FFmpeg is not installed or not accessible');
            return;
        }

        // Update status to processing
        $videoJob->update(['status' => 'processing', 'progress' => 10]);

        try {
            // Step 1: Collect image paths
            $imagePaths = $this->collectImages($yacht);

            if (empty($imagePaths)) {
                $this->failJob($videoJob, 'No images found for this yacht');
                return;
            }

            $videoJob->update(['image_count' => count($imagePaths), 'progress' => 20]);

            // Step 2: Create temp output paths
            $workDir = sys_get_temp_dir() . '/video_' . $videoJob->id;
            mkdir($workDir, 0777, true);

            $slideshowPath = "{$workDir}/slideshow.mp4";
            $withAudioPath = "{$workDir}/with_audio.mp4";
            $finalPath = "{$workDir}/final.mp4";

            // Step 3: Render slideshow
            $ffmpeg->renderSlideshow($imagePaths, $slideshowPath, 3);
            $videoJob->update(['progress' => 50]);

            // Step 4: Add audio
            $musicPath = $this->getMusicTrackPath($videoJob->music_track);
            $voicePath = $videoJob->has_voiceover && $videoJob->voiceover_path
                ? Storage::disk('public')->path($videoJob->voiceover_path)
                : null;

            if ($musicPath && $voicePath && file_exists($voicePath)) {
                // Mix music + voiceover
                $ffmpeg->mixAudioTracks($slideshowPath, $musicPath, $voicePath, $withAudioPath);
            } elseif ($musicPath) {
                // Music only
                $ffmpeg->addBackgroundMusic($slideshowPath, $musicPath, $withAudioPath);
            } else {
                // No audio
                $withAudioPath = $slideshowPath;
            }

            $videoJob->update(['progress' => 75]);

            // Step 5: Add watermark (optional)
            $logoPath = public_path('logos/schepen-kring.svg');
            if (file_exists($logoPath)) {
                $ffmpeg->addWatermark($withAudioPath, $logoPath, $finalPath);
            } else {
                $finalPath = $withAudioPath;
            }

            $videoJob->update(['progress' => 90]);

            // Step 6: Move to storage
            $storagePath = "videos/yacht-{$yacht->id}-" . time() . '.mp4';
            $storageFullPath = Storage::disk('public')->path($storagePath);

            // Ensure directory exists
            $dir = dirname($storageFullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            copy($finalPath, $storageFullPath);

            // Get video metadata
            $duration = $ffmpeg->getDuration($storageFullPath);
            $fileSize = filesize($storageFullPath);

            // Step 7: Update job as done
            $videoJob->update([
                'status' => 'done',
                'video_path' => $storagePath,
                'duration_seconds' => $duration,
                'file_size_bytes' => $fileSize,
                'progress' => 100,
            ]);

            // Cleanup temp files
            array_map('unlink', glob("{$workDir}/*"));
            rmdir($workDir);

            Log::info("Video rendered successfully for yacht {$yacht->id}: {$storagePath}");

        } catch (\Exception $e) {
            $this->failJob($videoJob, $e->getMessage());
            Log::error("Video rendering failed for yacht {$yacht->id}: " . $e->getMessage());
        }
    }

    /**
     * Collect all image paths for the yacht.
     */
    private function collectImages(Yacht $yacht): array
    {
        $paths = [];

        // Main image first
        if ($yacht->main_image) {
            $mainPath = Storage::disk('public')->path($yacht->main_image);
            if (file_exists($mainPath)) {
                $paths[] = $mainPath;
            }
        }

        // Gallery images
        if ($yacht->images) {
            foreach ($yacht->images as $image) {
                $imgPath = $image->image_path ?? $image->path ?? null;
                if ($imgPath) {
                    $fullPath = Storage::disk('public')->path($imgPath);
                    if (file_exists($fullPath)) {
                        $paths[] = $fullPath;
                    }
                }
            }
        }

        // Limit to 30 images
        return array_slice($paths, 0, 30);
    }

    /**
     * Get the full path for a music track.
     */
    private function getMusicTrackPath(?string $track): ?string
    {
        if (!$track) return null;

        $musicDir = resource_path('music');
        $path = "{$musicDir}/{$track}.mp3";

        return file_exists($path) ? $path : null;
    }

    /**
     * Mark the job as failed with an error message.
     */
    private function failJob(VideoJob $videoJob, string $error): void
    {
        $videoJob->update([
            'status' => 'failed',
            'error_log' => $error,
            'progress' => 0,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $videoJob = VideoJob::find($this->videoJobId);
        if ($videoJob) {
            $videoJob->update([
                'status' => 'failed',
                'error_log' => $exception->getMessage(),
                'progress' => 0,
            ]);
        }
    }
}
