<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\Cms\CmsComponentRegistry;
use App\Services\Cms\CmsPageService;
use App\Services\Cms\LanguageQualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CmsPageController extends Controller
{
    public function __construct(
        private readonly CmsPageService $cmsPages,
        private readonly LanguageQualityService $languageQuality,
    ) {
    }

    public function checkLanguageQuality(CmsPage $cmsPage): JsonResponse
    {
        return response()->json(['data' => $this->languageQuality->check($cmsPage)]);
    }

    public function componentRegistry(): JsonResponse
    {
        return response()->json(['data' => CmsComponentRegistry::definitions()]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'search' => 'nullable|string|max:120',
        ]);

        $query = CmsPage::query()->withCount('sections')->orderBy('name');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function show(CmsPage $cmsPage): JsonResponse
    {
        $cmsPage->load(['sections', 'createdBy:id,name,email']);

        return response()->json(['data' => $cmsPage]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9\-\/]+$/', Rule::unique('cms_pages', 'slug')],
            'name' => 'required|string|max:150',
            'seo' => 'nullable|array',
        ]);

        $page = $this->cmsPages->create($validated, $request->user());

        return response()->json(['data' => $page], 201);
    }

    public function updateMeta(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'seo' => 'nullable|array',
            'change_note' => 'nullable|string|max:255',
        ]);

        $page = $this->cmsPages->updateMeta($cmsPage, $validated, $request->user());

        return response()->json(['data' => $page]);
    }

    public function saveSections(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $validated = $request->validate([
            'sections' => 'present|array',
            'sections.*.component' => 'required|string',
            'sections.*.variant' => 'nullable|string',
            'sections.*.content' => 'nullable|array',
            'sections.*.sort_order' => 'nullable|integer',
            'sections.*.is_enabled' => 'nullable|boolean',
            'change_note' => 'nullable|string|max:255',
        ]);

        try {
            $page = $this->cmsPages->saveSections(
                $cmsPage,
                $validated['sections'],
                $request->user(),
                $validated['change_note'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'One or more sections are invalid.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(['data' => $page]);
    }

    public function publish(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $force = $request->boolean('force');

        try {
            $page = $this->cmsPages->publish($cmsPage, $request->user(), $force);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Language quality issues found — review before publishing.',
                'quality_issues' => $e->errors()['_issues'] ?? [],
            ], 422);
        }

        return response()->json(['data' => $page]);
    }

    public function schedule(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $validated = $request->validate([
            'publish_at' => 'required|date|after:now',
        ]);

        $page = $this->cmsPages->schedule($cmsPage, new \DateTimeImmutable($validated['publish_at']), $request->user());

        return response()->json(['data' => $page]);
    }

    public function archive(Request $request, CmsPage $cmsPage): JsonResponse
    {
        return response()->json(['data' => $this->cmsPages->archive($cmsPage, $request->user())]);
    }

    public function versions(CmsPage $cmsPage): JsonResponse
    {
        return response()->json([
            'data' => $cmsPage->versions()->with('createdBy:id,name,email')->get(),
        ]);
    }

    public function restoreVersion(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'required|integer|min:1',
        ]);

        $page = $this->cmsPages->restoreVersion($cmsPage, $validated['version'], $request->user());

        return response()->json(['data' => $page]);
    }
}
