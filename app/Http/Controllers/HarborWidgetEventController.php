<?php

namespace App\Http\Controllers;

use App\Models\HarborWidgetEvent;
use App\Models\HarborWidgetSetting;
use App\Models\User;
use App\Services\Ga4MeasurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HarborWidgetEventController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_token' => 'required|string',
            'event_type' => 'required|string|max:100',
            'placement' => 'nullable|string|max:100',
            'url' => 'nullable|string|max:2048',
            'referrer' => 'nullable|string|max:2048',
            'ref_code' => 'nullable|string|max:100',
            'ga_client_id' => 'nullable|string|max:120',
            'device_type' => 'nullable|string|max:50',
            'viewport_width' => 'nullable|integer|min:0',
            'viewport_height' => 'nullable|integer|min:0',
            'scroll_depth' => 'nullable|integer|min:0',
            'time_on_page_before_click' => 'nullable|integer|min:0',
            'widget_version' => 'nullable|string|max:50',
            'metadata' => 'nullable|array',
        ]);

        $harbor = User::where('partner_token', $validated['partner_token'])
            ->where('role', 'Partner')
            ->where('status', 'Active')
            ->first();

        if (!$harbor) {
            return response()->json(['message' => 'Invalid partner token.'], 404);
        }

        $setting = HarborWidgetSetting::where('harbor_id', $harbor->id)->first();
        if ($setting && $setting->domain && !empty($validated['url'])) {
            $requestHost = $this->normalizeHost($validated['url']);
            $settingHost = $this->normalizeHost($setting->domain);
            if ($requestHost && $settingHost && !$this->hostMatches($requestHost, $settingHost)) {
                return response()->json(['message' => 'Domain mismatch for partner.'], 403);
            }
        }

        $metadata = $validated['metadata'] ?? [];
        if (!empty($validated['ref_code'])) {
            $metadata['ref_code'] = $validated['ref_code'];
        }
        if (!empty($validated['ga_client_id'])) {
            $metadata['ga_client_id'] = $validated['ga_client_id'];
        }
        $metadata['ip'] = $request->ip();
        $metadata['user_agent'] = $request->userAgent();
        $metadata['request_id'] = $request->attributes->get('request_id');

        $deviceType = $validated['device_type'] ?? null;
        if (!$deviceType) {
            $ua = strtolower((string) $request->userAgent());
            $deviceType = Str::contains($ua, ['mobile', 'android', 'iphone', 'ipad']) ? 'mobile' : 'desktop';
        }

        $event = HarborWidgetEvent::create([
            'harbor_id' => $harbor->id,
            'event_type' => $validated['event_type'],
            'placement' => $validated['placement'] ?? $setting?->placement_default,
            'url' => $validated['url'] ?? null,
            'referrer' => $validated['referrer'] ?? null,
            'device_type' => $deviceType,
            'viewport_width' => $validated['viewport_width'] ?? null,
            'viewport_height' => $validated['viewport_height'] ?? null,
            'scroll_depth' => $validated['scroll_depth'] ?? null,
            'time_on_page_before_click' => $validated['time_on_page_before_click'] ?? null,
            'widget_version' => $validated['widget_version'] ?? $setting?->widget_version,
            'metadata' => $metadata,
        ]);

        $pagePath = $this->extractPath($validated['url'] ?? null);
        app(Ga4MeasurementService::class)->sendEvent($validated['event_type'], [
            'harbor_id' => $harbor->id,
            'ref' => $validated['ref_code'] ?? null,
            'placement' => $event->placement,
            'page_path' => $pagePath,
            'page_location' => $validated['url'] ?? null,
            'device_category' => $deviceType,
            'widget_version' => $event->widget_version,
        ], $validated['ga_client_id'] ?? null);

        return response()->json([
            'message' => 'Event stored',
            'event_id' => $event->id,
        ]);
    }

    private function normalizeHost(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!str_starts_with($value, 'http://') && !str_starts_with($value, 'https://')) {
            $value = 'https://' . $value;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        return strtolower($host);
    }

    private function hostMatches(string $requestHost, string $settingHost): bool
    {
        if ($requestHost === $settingHost) {
            return true;
        }

        return str_ends_with($requestHost, '.' . $settingHost);
    }

    private function extractPath(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }
        $path = parse_url($url, PHP_URL_PATH);
        return $path ?: '/';
    }
}
