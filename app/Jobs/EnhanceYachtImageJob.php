<?php

namespace App\Jobs;

use App\Models\YachtImage;
use App\Services\AiImageRotationService;
use App\Services\CloudinaryEnhanceService;
use App\Services\ImageProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2: Background AI enhancement via Cloudinary.
 * Runs AFTER the image is already visible to the user.
 * Silently upgrades the master WebP with an AI-enhanced version.
 */
class EnhanceYachtImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private int $yachtImageId
    ) {
        $this->queue = 'background';
    }

    public function handle(
        CloudinaryEnhanceService $enhancer,
        ImageProcessingService $processor,
        AiImageRotationService $rotationService
    ): void {
        $image = YachtImage::find($this->yachtImageId);

        if (!$image || !in_array($image->status, ['ready_for_review', 'approved'])) {
            return;
        }

        if (!$enhancer->isAvailable()) {
            Log::info("[EnhanceJob] Cloudinary not available, marking image #{$this->yachtImageId} as local-only");
            $image->update(['enhancement_method' => 'none']);
            return;
        }

        // Get the original temp file path
        $tempPath = $image->original_temp_url;
        $absolutePath = storage_path('app/public/' . $tempPath);

        if (!file_exists($absolutePath)) {
            Log::warning("[EnhanceJob] Original temp file missing for image #{$this->yachtImageId}, skipping");
            return;
        }

        try {
            $startTime = microtime(true);

            Log::info("[EnhanceJob] Starting AI enhancement for image #{$this->yachtImageId}");

            // Detect required rotation using AI Vision
            $rotationAngle = $rotationService->detectRotationAngle($absolutePath);

            $flags = $image->quality_flags ?? [];
            $aiAdjustments = [];
            if (!empty($flags['too_dark'])) {
                $aiAdjustments[] = 'Brightened shadows and lifted dark areas.';
            }
            if (!empty($flags['too_bright'])) {
                $aiAdjustments[] = 'Reduced harsh highlights and balanced exposure.';
            }
            if (!empty($flags['low_res'])) {
                $aiAdjustments[] = 'Upscaled details for a cleaner gallery result.';
            }
            if (!empty($flags['blurry'])) {
                $aiAdjustments[] = 'Applied clarity enhancement to improve sharpness.';
            }
            if ($rotationAngle > 0) {
                $aiAdjustments[] = "Rotated image {$rotationAngle} degrees to correct orientation.";
            }
            $hasFlags = !empty(array_filter($flags));

            // Send to Cloudinary for AI enhancement
            $enhancedPath = $enhancer->enhance($absolutePath, $flags, $rotationAngle);

            if (!$enhancedPath || !file_exists($enhancedPath)) {
                Log::warning("[EnhanceJob] Cloudinary returned no result for image #{$this->yachtImageId}");
                $flags['ai_rotation_angle'] = $rotationAngle;
                $flags['ai_adjustments'] = $aiAdjustments;
                $image->update([
                    'enhancement_method' => 'local',
                    'quality_flags' => $flags,
                ]);
                return;
            }

            // Re-process the enhanced image (resize, WebP, thumb)
            $yachtFolder = $image->yacht_id;
            $masterDir = "approved/master/{$yachtFolder}";
            $thumbDir  = "approved/thumb/{$yachtFolder}";

            $result = $processor->process($enhancedPath, $masterDir, $thumbDir);

            // Delete old master + thumb files
            $oldMaster = storage_path('app/public/' . $image->optimized_master_url);
            $oldThumb  = storage_path('app/public/' . $image->thumb_url);
            if (file_exists($oldMaster)) @unlink($oldMaster);
            if (file_exists($oldThumb)) @unlink($oldThumb);

            // Clean up temp enhanced file
            @unlink($enhancedPath);

            $elapsed = round(microtime(true) - $startTime, 2);

            // Update with enhanced version
            $image->update([
                'optimized_master_url' => $result['master_path'],
                'thumb_url'            => $result['thumb_path'],
                'url'                  => $result['master_path'],
                'enhancement_method'   => 'cloudinary',
                'quality_flags'        => array_merge($flags, [
                    'ai_rotation_angle' => $rotationAngle,
                    'ai_adjustments' => $aiAdjustments,
                ]),
            ]);

            Log::info("[EnhanceJob] ✅ Image #{$this->yachtImageId} enhanced in {$elapsed}s", [
                'master' => $result['master_path'],
            ]);

        } catch (\Throwable $e) {
            Log::error("[EnhanceJob] Failed for image #{$this->yachtImageId}: " . $e->getMessage());
            $flags = $image->quality_flags ?? [];
            $flags['ai_adjustments'] = $flags['ai_adjustments'] ?? ['Enhancement fallback applied.'];
            $image->update([
                'enhancement_method' => 'local',
                'quality_flags' => $flags,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[EnhanceJob] PERMANENT FAIL for image #{$this->yachtImageId}: " . $exception->getMessage());
        $image = YachtImage::find($this->yachtImageId);
        if ($image) {
            $image->update(['enhancement_method' => 'local']);
        }
    }
}
