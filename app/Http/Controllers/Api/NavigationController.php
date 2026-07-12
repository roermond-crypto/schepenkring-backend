<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NavItem;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

/**
 * Public read endpoint for PublicHeader/PublicFooter — only ever returns
 * visible items. Un-authenticated, so required_role-gated items are
 * filtered out entirely rather than sent-but-hidden (no reason to leak
 * their existence to a logged-out visitor).
 */
class NavigationController extends Controller
{
    public function index(): JsonResponse
    {
        $header = NavItem::query()
            ->with(['children' => fn ($q) => $q->visible()->whereNull('required_role')])
            ->where('location', NavItem::LOCATION_HEADER)
            ->visible()
            ->topLevel()
            ->whereNull('required_role')
            ->orderBy('sort_order')
            ->get();

        $footerItems = NavItem::query()
            ->where('location', NavItem::LOCATION_FOOTER)
            ->visible()
            ->topLevel()
            ->whereNull('required_role')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (NavItem $item) => $item->footer_column ?? 'general');

        return response()->json([
            'data' => [
                'header' => $header,
                'footer' => [
                    'columns' => $footerItems,
                    'settings' => SiteSetting::current(),
                ],
            ],
        ]);
    }
}
