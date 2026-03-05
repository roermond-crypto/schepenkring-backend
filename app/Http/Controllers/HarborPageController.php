<?php

namespace App\Http\Controllers;

use App\Models\Harbor;
use App\Models\HarborPage;
use App\Services\LocaleService;
use Illuminate\Http\Request;

class HarborPageController extends Controller
{
    /** GET /api/harbor-pages/{harborId} — get page content (default locale: nl) */
    public function show($harborId, Request $request)
    {
        $localeService = app(LocaleService::class);
        $localeInfo = $localeService->resolve($request, $request->user());
        $requestedLocale = $localeInfo['locale'];
        $fallbacks = $localeInfo['fallbacks'];

        $harbor = Harbor::findOrFail($harborId);
        $pages = HarborPage::where('harbor_id', $harborId)
            ->whereIn('locale', $fallbacks)
            ->get()
            ->keyBy('locale');

        $page = null;
        $usedLocale = $requestedLocale;
        foreach ($fallbacks as $candidate) {
            $page = $pages->get($candidate);
            if ($page) {
                $usedLocale = $candidate;
                break;
            }
        }

        return response()->json([
            'harbor'  => $harbor,
            'page'    => $page,
            'requested_locale' => $requestedLocale,
            'fallback_locale_used' => $usedLocale !== $requestedLocale ? $usedLocale : null,
        ]);
    }

    /** GET /api/harbor-pages/{harborId}/{locale} — locale-specific page */
    public function showByLocale($harborId, $locale)
    {
        $harbor = Harbor::findOrFail($harborId);
        $localeService = app(LocaleService::class);
        $requestedLocale = strtolower((string) $locale);
        if (!in_array($requestedLocale, $localeService->supported(), true)) {
            $requestedLocale = $localeService->default();
        }

        $fallbacks = $localeService->fallbackChain($requestedLocale);
        $pages = HarborPage::where('harbor_id', $harborId)
            ->whereIn('locale', $fallbacks)
            ->get()
            ->keyBy('locale');

        $page = null;
        $usedLocale = $requestedLocale;
        foreach ($fallbacks as $candidate) {
            $page = $pages->get($candidate);
            if ($page) {
                $usedLocale = $candidate;
                break;
            }
        }

        return response()->json([
            'harbor'  => $harbor,
            'page'    => $page,
            'requested_locale' => $requestedLocale,
            'fallback_locale_used' => $usedLocale !== $requestedLocale ? $usedLocale : null,
        ]);
    }
}
