<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessYachtImageJob;
use App\Models\Yacht;
use App\Models\YachtImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImagePipelineController extends Controller
{
    /**
     * Minimum approved images required to unlock Step 2.
     */
    protected int $minApproved;

    public function __construct()
    {
        $this->minApproved = (int) config('services.pipeline.min_approved_images', 1);
    }

    /**
     * POST /yachts/{yachtId}/images/upload
     * Upload 1–30 images, store to original_temp/, dispatch processing jobs.
     */
    public function upload(Request $request, $yachtId): JsonResponse
    {
        $request->validate([
            'images'   => 'required|array|min:1|max:30',
            'images.*' => 'required|file|max:15360', // 15MB each
        ]);

        $yacht = Yacht::findOrFail($yachtId);

        // Check current image count
        $currentCount = $yacht->images()->whereNotIn('status', ['deleted'])->count();
        $newCount = count($request->file('images'));

        if ($currentCount + $newCount > 30) {
            return response()->json([
                'error' => 'Maximum 30 images allowed. You have ' . $currentCount . ' images.',
            ], 422);
        }

        $uploaded = [];

        foreach ($request->file('images') as $index => $file) {
            try {
                // Store in original_temp/
                $folderName = $yacht->vessel_id ?? $yacht->id;
                $fileName = uniqid('orig_') . '.' . $file->getClientOriginalExtension();
                $tempPath = $file->storeAs(
                    "original_temp/{$folderName}",
                    $fileName,
                    'public'
                );

                // Get category from request if provided
                $categories = $request->input('categories', []);
                $category = $categories[$index] ?? 'General';

                // Create DB record
                $image = $yacht->images()->create([
                    'url'               => $tempPath, // Will be updated after processing
                    'original_temp_url' => $tempPath,
                    'original_name'     => $file->getClientOriginalName(),
                    'category'          => $category,
                    'part_name'         => $category,
                    'status'            => 'processing',
                    'keep_original'     => false,
                    'sort_order'        => $currentCount + $index,
                ]);

                // Dispatch processing job
                ProcessYachtImageJob::dispatch($image->id);

                $uploaded[] = $image->fresh();
            } catch (\Throwable $e) {
                Log::error("Image upload failed for yacht #{$yachtId}: " . $e->getMessage());
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => count($uploaded) . ' images uploaded and queued for processing.',
            'images'  => $uploaded,
        ]);
    }

    /**
     * GET /yachts/{yachtId}/images
     * List all images with statuses and quality info.
     */
    public function index($yachtId): JsonResponse
    {
        $yacht = Yacht::findOrFail($yachtId);

        $images = $yacht->images()
            ->whereNotIn('status', ['deleted'])
            ->orderBy('sort_order')
            ->get();

        $approvedCount = $images->where('status', 'approved')->count();
        $processingCount = $images->where('status', 'processing')->count();
        $enhancingCount = $images->where('enhancement_method', 'pending')->count();
        $readyCount = $images->where('status', 'ready_for_review')->count();

        $isStep2Unlocked = $approvedCount >= $this->minApproved && ($processingCount + $enhancingCount) === 0;

        return response()->json([
            'images'  => $images,
            'stats'   => [
                'total'        => $images->count(),
                'approved'     => $approvedCount,
                'processing'   => $processingCount + $enhancingCount,
                'ready'        => $readyCount,
                'min_required' => $this->minApproved,
            ],
            'step2_unlocked' => $isStep2Unlocked,
        ]);
    }

    /**
     * POST /yachts/{yachtId}/images/{imageId}/approve
     * Approve a single image.
     */
    public function approve($yachtId, $imageId): JsonResponse
    {
        $image = YachtImage::where('yacht_id', $yachtId)
            ->where('id', $imageId)
            ->firstOrFail();

        if (!in_array($image->status, ['ready_for_review', 'processing_failed'])) {
            return response()->json([
                'error' => 'Image cannot be approved in its current status: ' . $image->status,
            ], 422);
        }

        $image->update(['status' => 'approved']);

        // Handle original cleanup if keep_original is false
        if (!$image->keep_original && $image->original_temp_url) {
            $this->scheduleOriginalCleanup($image);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Image approved.',
            'image'   => $image->fresh(),
        ]);
    }

    /**
     * POST /yachts/{yachtId}/images/{imageId}/delete
     * Soft-delete (set status) and remove files.
     */
    public function deleteImage($yachtId, $imageId): JsonResponse
    {
        $image = YachtImage::where('yacht_id', $yachtId)
            ->where('id', $imageId)
            ->firstOrFail();

        // Delete all associated files
        $pathsToDelete = array_filter([
            $image->original_temp_url,
            $image->optimized_master_url,
            $image->thumb_url,
            $image->original_kept_url,
        ]);

        foreach ($pathsToDelete as $path) {
            Storage::disk('public')->delete($path);
        }

        $image->update(['status' => 'deleted']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Image deleted.',
        ]);
    }

    /**
     * POST /yachts/{yachtId}/images/{imageId}/toggle-keep-original
     * Toggle the keep_original flag.
     */
    public function toggleKeepOriginal($yachtId, $imageId): JsonResponse
    {
        $image = YachtImage::where('yacht_id', $yachtId)
            ->where('id', $imageId)
            ->firstOrFail();

        $newValue = !$image->keep_original;

        if ($newValue && $image->original_temp_url) {
            // Copy original to original_kept/
            $keptPath = str_replace('original_temp/', 'original_kept/', $image->original_temp_url);

            // Ensure directory exists
            $keptDir = dirname(storage_path('app/public/' . $keptPath));
            if (!is_dir($keptDir)) {
                mkdir($keptDir, 0755, true);
            }

            Storage::disk('public')->copy($image->original_temp_url, $keptPath);

            $image->update([
                'keep_original'     => true,
                'original_kept_url' => $keptPath,
            ]);
        } else {
            // Remove kept original if toggled off
            if ($image->original_kept_url) {
                Storage::disk('public')->delete($image->original_kept_url);
            }

            $image->update([
                'keep_original'     => false,
                'original_kept_url' => null,
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Keep original ' . ($newValue ? 'enabled' : 'disabled'),
            'image'   => $image->fresh(),
        ]);
    }

    /**
     * POST /yachts/{yachtId}/images/approve-all
     * Bulk approve all ready_for_review images.
     */
    public function approveAll($yachtId): JsonResponse
    {
        $yacht = Yacht::findOrFail($yachtId);

        $updated = $yacht->images()
            ->where('status', 'ready_for_review')
            ->update(['status' => 'approved']);

        // Cleanup temp originals where keep_original = false
        $toCleanup = $yacht->images()
            ->where('status', 'approved')
            ->where('keep_original', false)
            ->whereNotNull('original_temp_url')
            ->get();

        foreach ($toCleanup as $image) {
            $this->scheduleOriginalCleanup($image);
        }

        $approvedCount = $yacht->images()->where('status', 'approved')->count();
        $processingCount = $yacht->images()->where('status', 'processing')->count()
            + $yacht->images()->where('enhancement_method', 'pending')->count();

        return response()->json([
            'status'         => 'success',
            'message'        => "{$updated} images approved.",
            'approved_count' => $approvedCount,
            'step2_unlocked' => $approvedCount >= $this->minApproved && $processingCount === 0,
        ]);
    }

    /**
     * GET /yachts/{yachtId}/step2-unlocked
     * Check if the approval gate passes.
     */
    public function step2Unlocked($yachtId): JsonResponse
    {
        $yacht = Yacht::findOrFail($yachtId);

        $approvedCount = $yacht->images()->where('status', 'approved')->count();
        $processingCount = $yacht->images()->where('status', 'processing')->count()
            + $yacht->images()->where('enhancement_method', 'pending')->count();
        $totalCount = $yacht->images()->whereNotIn('status', ['deleted'])->count();

        $unlocked = $approvedCount >= $this->minApproved && $processingCount === 0;

        return response()->json([
            'step2_unlocked'   => $unlocked,
            'approved_count'   => $approvedCount,
            'processing_count' => $processingCount,
            'total_count'      => $totalCount,
            'min_required'     => $this->minApproved,
            'reason'           => !$unlocked
                ? ($processingCount > 0
                    ? "Still processing {$processingCount} images."
                    : "Need at least {$this->minApproved} approved images (have {$approvedCount}).")
                : null,
        ]);
    }

    /**
     * Schedule cleanup of original temp file (immediate for now).
     */
    protected function scheduleOriginalCleanup(YachtImage $image): void
    {
        if ($image->original_temp_url && !$image->keep_original) {
            Storage::disk('public')->delete($image->original_temp_url);
            Log::info("Cleaned up temp original: {$image->original_temp_url}");
        }
    }
}
