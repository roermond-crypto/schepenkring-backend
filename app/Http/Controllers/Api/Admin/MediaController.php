<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Cms\MediaAiDraftService;
use App\Services\ImageProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // all | unused | needs_alt_text | needs_seo | large_file_size
            'filter' => 'nullable|string',
            'search' => 'nullable|string|max:150',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Media::query()->withCount('usages')->orderByDesc('created_at');

        if (! empty($validated['search'])) {
            $query->where('original_name', 'like', '%' . $validated['search'] . '%');
        }

        switch ($validated['filter'] ?? 'all') {
            case 'unused':
                $query->doesntHave('usages');
                break;
            case 'needs_alt_text':
                $query->where(fn ($q) => $q
                    ->whereNull('alt_text')
                    ->orWhereJsonLength('alt_text', 0));
                break;
            case 'needs_seo':
                $query->where(fn ($q) => $q
                    ->whereNull('seo_title')
                    ->orWhereJsonLength('seo_title', 0));
                break;
            case 'large_file_size':
                $query->where('file_size', '>', 2 * 1024 * 1024); // >2MB
                break;
            case 'recently_uploaded':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
        }

        return response()->json($query->paginate($validated['per_page'] ?? 40));
    }

    public function upload(Request $request, ImageProcessingService $imageProcessing): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'required|file|image|max:15360', // 15MB each
        ]);

        $uploaded = [];

        foreach ($request->file('files') as $file) {
            try {
                $tempPath = $file->store('media/original_temp', 'public');
                $absoluteTempPath = storage_path('app/public/' . $tempPath);

                $processed = $imageProcessing->process($absoluteTempPath, 'media/optimized', 'media/thumb');

                $media = Media::create([
                    'disk_path' => $processed['master_path'],
                    'thumb_path' => $processed['thumb_path'],
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'width' => $processed['width'],
                    'height' => $processed['height'],
                    'status' => Media::STATUS_OPTIMIZED,
                    'created_by_id' => $request->user()?->id,
                ]);

                $uploaded[] = $media;
            } catch (\Throwable $e) {
                Log::error('[MediaController] Upload processing failed: ' . $e->getMessage());
            }
        }

        return response()->json(['data' => $uploaded], 201);
    }

    public function update(Request $request, Media $medium): JsonResponse
    {
        $validated = $request->validate([
            'alt_text' => 'nullable|array',
            'caption' => 'nullable|array',
            'seo_title' => 'nullable|array',
            'focal_point_x' => 'nullable|numeric|min:0|max:1',
            'focal_point_y' => 'nullable|numeric|min:0|max:1',
            'crop_data' => 'nullable|array',
        ]);

        $medium->update([
            ...$validated,
            'ai_alt_text_is_draft' => array_key_exists('alt_text', $validated) ? false : $medium->ai_alt_text_is_draft,
            'ai_seo_title_is_draft' => array_key_exists('seo_title', $validated) ? false : $medium->ai_seo_title_is_draft,
        ]);

        return response()->json(['data' => $medium->fresh()]);
    }

    public function destroy(Media $medium): JsonResponse
    {
        $medium->delete();

        return response()->json(['message' => 'Media deleted.']);
    }

    public function usages(Media $medium): JsonResponse
    {
        return response()->json(['data' => $medium->usages()->get()]);
    }

    public function generateAiAltText(Media $medium, MediaAiDraftService $aiDraft): JsonResponse
    {
        $altText = $aiDraft->generateAltText($medium);
        if ($altText === null) {
            return response()->json(['message' => 'AI alt text generation unavailable or failed.'], 422);
        }

        return response()->json(['data' => $medium->fresh()]);
    }

    public function generateAiSeoTitle(Media $medium, MediaAiDraftService $aiDraft): JsonResponse
    {
        $seoTitle = $aiDraft->generateSeoTitle($medium);
        if ($seoTitle === null) {
            return response()->json(['message' => 'AI SEO title generation unavailable or failed.'], 422);
        }

        return response()->json(['data' => $medium->fresh()]);
    }
}
