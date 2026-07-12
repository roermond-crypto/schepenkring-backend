<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NavItem;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NavigationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location' => 'nullable|string|in:header,footer',
        ]);

        $query = NavItem::query()->with('children')->topLevel()->orderBy('sort_order');

        if (! empty($validated['location'])) {
            $query->where('location', $validated['location']);
        }

        return response()->json([
            'data' => $query->get(),
            'site_settings' => SiteSetting::current(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $item = NavItem::create($validated);

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, NavItem $navItem): JsonResponse
    {
        $validated = $this->validated($request, $navItem);

        $navItem->update($validated);

        return response()->json(['data' => $navItem->fresh()]);
    }

    public function destroy(NavItem $navItem): JsonResponse
    {
        $navItem->delete();

        return response()->json(['message' => 'Nav item deleted.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:nav_items,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['items'] as $item) {
            NavItem::whereKey($item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Order updated.']);
    }

    public function updateSiteSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'footer_tagline' => 'nullable|array',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:60',
            'contact_address' => 'nullable|string|max:255',
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links|string|max:60',
            'social_links.*.url' => 'required_with:social_links|url|max:255',
        ]);

        $settings = SiteSetting::current();
        $settings->update($validated);

        return response()->json(['data' => $settings->fresh()]);
    }

    private function validated(Request $request, ?NavItem $existing = null): array
    {
        $parentRule = Rule::exists('nav_items', 'id');
        if ($existing) {
            $parentRule = $parentRule->where(fn ($q) => $q->whereKeyNot($existing->id));
        }

        return $request->validate([
            'location' => ['required', Rule::in([NavItem::LOCATION_HEADER, NavItem::LOCATION_FOOTER])],
            'footer_column' => 'nullable|string|max:80',
            'parent_id' => ['nullable', 'integer', $parentRule],
            'label' => 'required|array',
            'url' => 'required|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_visible' => 'nullable|boolean',
            'open_in_new_tab' => 'nullable|boolean',
            'required_role' => 'nullable|string|max:60',
        ]);
    }
}
