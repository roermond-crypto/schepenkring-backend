<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EnhanceYachtImageJob;
use App\Jobs\ProcessYachtImageJob;
use App\Models\User;
use App\Models\Yacht;
use App\Models\YachtImage;
use App\Services\LocationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImagePipelineController extends Controller
{
    private const VALID_CATEGORIES = ['Exterior', 'Interior', 'Engine Room', 'Bridge', 'General'];
    private const CATEGORY_ORDER = [
        'Exterior' => 0,
        'Bridge' => 1,
        'Interior' => 2,
        'Engine Room' => 3,
        'General' => 4,
    ];

    /**
     * Minimum approved images required to unlock Step 2.
     */
    protected int $minApproved;

    public function __construct(private readonly LocationAccessService $locationAccess)
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

        $yacht = $this->findAuthorizedYacht($request, $yachtId);

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
                    'url'               => $tempPath, // Points to original_temp until optimized
                    'original_temp_url' => $tempPath,
                    'original_name'     => $file->getClientOriginalName(),
                    'category'          => $category,
                    'part_name'         => $category,
                    'status'            => 'ready_for_review', // Instant ready for review/approval
                    'keep_original'     => false,
                    'sort_order'        => $currentCount + $index,
                ]);

                // Queue the optimization after the HTTP response is sent so uploads return faster.
                ProcessYachtImageJob::dispatchAfterResponse($image->id);

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
    public function index(Request $request, $yachtId): JsonResponse
    {
        $yacht = $this->findAuthorizedYacht($request, $yachtId);

        $images = $yacht->images()
            ->whereNotIn('status', ['deleted'])
            ->orderBy('sort_order')
            ->get();

        $approvedCount = $images->where('status', 'approved')->count();
        $processingCount = $images->where('status', 'processing')->count();
        $enhancingCount = $images->where('enhancement_method', 'pending')->count();
        $readyCount = $images->where('status', 'ready_for_review')->count();

        // Step 2 should not be blocked by background processing/enhancement.
        $isStep2Unlocked = $approvedCount >= $this->minApproved;

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
    public function approve(Request $request, $yachtId, $imageId): JsonResponse
    {
        $this->findAuthorizedYacht($request, $yachtId);

        $image = YachtImage::where('yacht_id', $yachtId)
            ->where('id', $imageId)
            ->firstOrFail();

        if (!in_array($image->status, ['ready_for_review', 'processing_failed'])) {
            return response()->json([
                'error' => 'Image cannot be approved in its current status: ' . $image->status,
            ], 422);
        }

        $image->update(['status' => 'approved']);

        // Trigger AI enhancement if not already done
        if ($image->enhancement_method !== 'cloudinary') {
            EnhanceYachtImageJob::dispatch($image->id)->delay(now()->addSeconds(1));
        }

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
    public function deleteImage(Request $request, $yachtId, $imageId): JsonResponse
    {
        $this->findAuthorizedYacht($request, $yachtId);

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
    public function toggleKeepOriginal(Request $request, $yachtId, $imageId): JsonResponse
    {
        $this->findAuthorizedYacht($request, $yachtId);

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

            $updateData = [
                'keep_original'     => true,
                'original_kept_url' => $keptPath,
            ];

            // Auto-approve if currently ready for review
            if ($image->status === 'ready_for_review') {
                $updateData['status'] = 'approved';
            }

            $image->update($updateData);
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
     * POST /yachts/{yachtId}/images/reorder
     * Persist manual drag-and-drop image ordering.
     */
    public function reorder(Request $request, $yachtId): JsonResponse
    {
        $request->validate([
            'image_ids' => 'required|array|min:1',
            'image_ids.*' => 'required|integer',
        ]);

        $yacht = $this->findAuthorizedYacht($request, $yachtId);
        $images = $yacht->images()->whereNotIn('status', ['deleted'])->get(['id']);
        $existingIds = $images->pluck('id')->all();
        $incomingIds = array_values(array_map('intval', $request->input('image_ids', [])));

        sort($existingIds);
        $sortedIncoming = $incomingIds;
        sort($sortedIncoming);

        if ($existingIds !== $sortedIncoming) {
            return response()->json([
                'error' => 'Image order payload does not match existing yacht images.',
            ], 422);
        }

        DB::transaction(function () use ($incomingIds) {
            foreach ($incomingIds as $index => $imageId) {
                YachtImage::whereKey($imageId)->update(['sort_order' => $index]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Image order updated.',
        ]);
    }

    /**
     * POST /yachts/{yachtId}/images/auto-classify
     * Reclassify stored images into gallery buckets and regroup sort order.
     */
    public function autoClassify(Request $request, $yachtId): JsonResponse
    {
        $yacht = $this->findAuthorizedYacht($request, $yachtId);
        $images = $yacht->images()
            ->whereNotIn('status', ['deleted'])
            ->orderBy('sort_order')
            ->get();

        foreach ($images as $image) {
            $category = $this->classifyStoredImage($image);
            $flags = $image->quality_flags ?? [];
            $flags['ai_category_source'] = 'auto_classify';

            $image->update([
                'category' => $category,
                'part_name' => $category,
                'quality_flags' => $flags,
            ]);
        }

        $refreshed = $yacht->images()
            ->whereNotIn('status', ['deleted'])
            ->orderBy('sort_order')
            ->get()
            ->sortBy(function (YachtImage $image) {
                return [
                    self::CATEGORY_ORDER[$image->category ?? 'General'] ?? 999,
                    $image->sort_order,
                    $image->id,
                ];
            })
            ->values();

        DB::transaction(function () use ($refreshed) {
            foreach ($refreshed as $index => $image) {
                $image->update(['sort_order' => $index]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Images auto-classified.',
            'images' => $yacht->images()
                ->whereNotIn('status', ['deleted'])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    /**
     * POST /yachts/{yachtId}/images/approve-all
     * Bulk approve all ready_for_review images.
     */
    public function approveAll(Request $request, $yachtId): JsonResponse
    {
        $yacht = $this->findAuthorizedYacht($request, $yachtId);

        $updated = $yacht->images()
            ->where('status', 'ready_for_review')
            ->update(['status' => 'approved']);

        // Trigger AI enhancement for all newly approved images
        $yacht->images()
            ->where('status', 'approved')
            ->where('enhancement_method', '!=', 'cloudinary')
            ->each(function ($image) {
                EnhanceYachtImageJob::dispatch($image->id)->delay(now()->addSeconds(1));
            });

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

        return response()->json([
            'status'         => 'success',
            'message'        => "{$updated} images approved.",
            'approved_count' => $approvedCount,
            'step2_unlocked' => $approvedCount >= $this->minApproved,
        ]);
    }

    /**
     * GET /yachts/{yachtId}/step2-unlocked
     * Check if the approval gate passes.
     */
    public function step2Unlocked(Request $request, $yachtId): JsonResponse
    {
        $yacht = $this->findAuthorizedYacht($request, $yachtId);

        $approvedCount = $yacht->images()->where('status', 'approved')->count();
        $processingCount = $yacht->images()->where('status', 'processing')->count()
            + $yacht->images()->where('enhancement_method', 'pending')->count();
        $totalCount = $yacht->images()->whereNotIn('status', ['deleted'])->count();

        // Unlock based on approvals only; processing continues in background.
        $unlocked = $approvedCount >= $this->minApproved;

        return response()->json([
            'step2_unlocked'   => $unlocked,
            'approved_count'   => $approvedCount,
            'processing_count' => $processingCount,
            'total_count'      => $totalCount,
            'min_required'     => $this->minApproved,
            'reason'           => !$unlocked
                ? "Need at least {$this->minApproved} approved images (have {$approvedCount})."
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

    private function findAuthorizedYacht(Request $request, int|string $yachtId): Yacht
    {
        $yacht = Yacht::query()->with('owner')->findOrFail($yachtId);
        $this->authorizeYachtAccess($request->user(), $yacht);

        return $yacht;
    }

    private function authorizeYachtAccess(?User $user, Yacht $yacht): void
    {
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isClient() && (int) $yacht->user_id === (int) $user->id) {
            return;
        }

        if ($user->isEmployee()) {
            $locationId = $this->resolveYachtLocationId($yacht);
            if ($locationId !== null && $this->locationAccess->sharesLocation($user, $locationId)) {
                return;
            }
        }

        abort(403, 'Forbidden');
    }

    private function resolveYachtLocationId(Yacht $yacht): ?int
    {
        if ($yacht->ref_harbor_id) {
            return (int) $yacht->ref_harbor_id;
        }

        if ($yacht->owner?->client_location_id) {
            return (int) $yacht->owner->client_location_id;
        }

        if (! $yacht->user_id) {
            return null;
        }

        return User::query()
            ->whereKey($yacht->user_id)
            ->value('client_location_id');
    }

    private function classifyStoredImage(YachtImage $image): string
    {
        $absolutePath = $this->resolveStoredImagePath($image);
        if (!$absolutePath) {
            return $this->inferCategoryFromFilename($image->original_name);
        }

        $apiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');
        if (!$apiKey) {
            return $this->inferCategoryFromFilename($image->original_name);
        }

        try {
            $imageData = base64_encode(file_get_contents($absolutePath));
            $model = 'gemini-2.5-flash';
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(20)->post($endpoint, [
                'contents' => [[
                    'parts' => [
                        ['text' => 'Return only one word: Exterior, Interior, Engine Room, Bridge, or General.'],
                        ['inline_data' => [
                            'mime_type' => mime_content_type($absolutePath) ?: 'image/jpeg',
                            'data' => $imageData,
                        ]],
                    ],
                ]],
            ]);

            if ($response->successful()) {
                $text = data_get($response->json(), 'candidates.0.content.parts.0.text', 'General');
                $category = trim((string) preg_replace('/[^A-Za-z\s]/', '', (string) $text));
                if (in_array($category, self::VALID_CATEGORIES, true)) {
                    return $category;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ImagePipeline] Auto-classify fallback triggered', [
                'image_id' => $image->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->inferCategoryFromFilename($image->original_name);
    }

    private function resolveStoredImagePath(YachtImage $image): ?string
    {
        $candidates = [
            $image->original_kept_url,
            $image->original_temp_url,
            $image->optimized_master_url,
            $image->thumb_url,
            $image->url,
        ];

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }

            $absolutePath = storage_path('app/public/' . ltrim($candidate, '/'));
            if (file_exists($absolutePath)) {
                return $absolutePath;
            }
        }

        return null;
    }

    private function inferCategoryFromFilename(?string $originalName): string
    {
        $normalized = strtolower((string) $originalName);
        if (str_contains($normalized, 'engine')) {
            return 'Engine Room';
        }

        if (str_contains($normalized, 'bridge') || str_contains($normalized, 'helm') || str_contains($normalized, 'cockpit')) {
            return 'Bridge';
        }

        if (
            str_contains($normalized, 'interior') ||
            str_contains($normalized, 'cabin') ||
            str_contains($normalized, 'salon') ||
            str_contains($normalized, 'kitchen') ||
            str_contains($normalized, 'bed')
        ) {
            return 'Interior';
        }

        if (
            str_contains($normalized, 'exterior') ||
            str_contains($normalized, 'outside') ||
            str_contains($normalized, 'deck') ||
            str_contains($normalized, 'hull')
        ) {
            return 'Exterior';
        }

        return 'General';
    }
}
