<?php

namespace App\Http\Controllers;

use App\Services\AttributionService;
use App\Services\Ga4MeasurementService;
use Illuminate\Http\Request;

class HarborFunnelEventController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_name' => 'required|string|max:100',
            'boat_id' => 'nullable|integer',
            'language' => 'nullable|string|max:10',
            'page_path' => 'nullable|string|max:2048',
            'page_location' => 'nullable|string|max:2048',
            'device_category' => 'nullable|string|max:30',
        ]);

        $allowed = [
            'boat_form_started',
            'boat_submitted',
            'auction_started',
            'winning_bid_selected',
            'deal_completed',
        ];

        if (!in_array($validated['event_name'], $allowed, true)) {
            return response()->json(['message' => 'Invalid event name'], 422);
        }

        $attribution = app(AttributionService::class)->getAttribution($request);
        $clientId = app(AttributionService::class)->getGaClientId($request);

        $deviceCategory = $validated['device_category'] ?? $this->detectDeviceCategory($request->userAgent());

        app(Ga4MeasurementService::class)->sendEvent($validated['event_name'], [
            'harbor_id' => $attribution['harbor_id'] ?? null,
            'ref' => $attribution['ref_code'] ?? null,
            'boat_id' => $validated['boat_id'] ?? null,
            'language' => $validated['language'] ?? $request->header('Accept-Language'),
            'device_category' => $deviceCategory,
            'page_path' => $validated['page_path'] ?? null,
            'page_location' => $validated['page_location'] ?? null,
        ], $clientId, (string) optional($request->user())->id);

        return response()->json(['message' => 'Event tracked']);
    }

    private function detectDeviceCategory(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        if ($ua === '') {
            return 'unknown';
        }
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) {
            return 'mobile';
        }
        return 'desktop';
    }
}
