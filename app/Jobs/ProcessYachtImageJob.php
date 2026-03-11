<?php

namespace App\Jobs;

use App\Models\YachtImage;
use App\Services\ImageProcessingService;
use App\Services\ImageQualityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 1: INSTANT local processing (~200ms per image).
 * Quality scan → EXIF rotate → resize → WebP → thumbnail.
 * Then dispatches EnhanceYachtImageJob for background AI enhancement.
 */
class ProcessYachtImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        private int $yachtImageId
    ) {}

    public function handle(
        ImageProcessingService $processor,
        ImageQualityService $qualityService
    ): void {
        $image = YachtImage::find($this->yachtImageId);

        if (!$image) {
            Log::warning("ProcessYachtImageJob: Image #{$this->yachtImageId} not found, skipping.");
            return;
        }

        if (!in_array($image->status, ['processing', 'ready_for_review', 'approved'])) {
            Log::info("ProcessYachtImageJob: Image #{$this->yachtImageId} status is '{$image->status}', skipping.");
            return;
        }

        $tempPath = $image->original_temp_url;
        $absolutePath = storage_path('app/public/' . $tempPath);

        if (!file_exists($absolutePath)) {
            Log::error("ProcessYachtImageJob: File not found at {$absolutePath}");
            // Only fail if we haven't already moved past 'processing'
            if ($image->status === 'processing') {
                $image->update(['status' => 'processing_failed']);
            }
            return;
        }

        try {
            $startTime = microtime(true);

            // ── 1. Quality scoring ──
            $quality = $qualityService->score($absolutePath);

            // ── 2. Local processing (EXIF rotate, resize, WebP, thumbnail) ──
            $yachtFolder = $image->yacht_id;
            $masterDir = "approved/master/{$yachtFolder}";
            $thumbDir  = "approved/thumb/{$yachtFolder}";

            $result = $processor->process($absolutePath, $masterDir, $thumbDir);

            $elapsed = round(microtime(true) - $startTime, 2);

            Log::info("ProcessYachtImageJob: ⚡ Image #{$this->yachtImageId} optimized in {$elapsed}s", [
                'master'     => $result['master_path'],
                'dimensions' => "{$result['width']}x{$result['height']}",
                'quality'    => $quality['label'],
            ]);

            // ── 3. Update DB → update URL and thumb silently ──
            $updateData = [
                'optimized_master_url' => $result['master_path'],
                'thumb_url'            => $result['thumb_path'],
                'quality_score'        => $quality['score'],
                'quality_flags'        => $quality['flags'],
                'url'                  => $result['master_path'], // Silent upgrade to WebP
                'enhancement_method'   => 'pending',
            ];

            // If it was somehow still in 'processing', move to 'ready_for_review'
            if ($image->status === 'processing') {
                $updateData['status'] = 'ready_for_review';
            }

            $image->update($updateData);



        } catch (\Throwable $e) {
            Log::error("ProcessYachtImageJob: Failed for image #{$this->yachtImageId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $image->update(['status' => 'processing_failed']);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessYachtImageJob FAILED permanently for image #{$this->yachtImageId}: " . $exception->getMessage());
        $image = YachtImage::find($this->yachtImageId);
        if ($image) {
            $image->update(['status' => 'processing_failed']);
        }
    }
}
