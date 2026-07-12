<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;

/**
 * Public read endpoint — only ever returns published content. This is the
 * "Laravel API provides content, frontend renders components" half of the
 * CMS: public pages are meant to fetch from here instead of hardcoding
 * text, though migrating the existing pages to actually do so is a later
 * phase (this endpoint exists now so that migration has something to
 * point at).
 */
class CmsPageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('status', CmsPage::STATUS_PUBLISHED)
            ->with(['sections' => fn ($q) => $q->where('is_enabled', true)->orderBy('sort_order')])
            ->firstOrFail();

        return response()->json(['data' => $page]);
    }
}
