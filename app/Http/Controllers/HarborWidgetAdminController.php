<?php

namespace App\Http\Controllers;

use App\Models\HarborWidgetAiAdvice;
use App\Models\HarborWidgetDailySnapshot;
use App\Models\HarborWidgetSetting;
use App\Models\HarborWidgetWeeklyMetric;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HarborWidgetAdminController extends Controller
{
    public function overview(Request $request)
    {
        $partners = User::where('role', 'Partner')->orderBy('name')->get();
        $harborIds = $partners->pluck('id');

        $settings = HarborWidgetSetting::whereIn('harbor_id', $harborIds)->get()->keyBy('harbor_id');

        $metrics = HarborWidgetWeeklyMetric::whereIn('harbor_id', $harborIds)
            ->orderBy('week_start', 'desc')
            ->get()
            ->groupBy('harbor_id')
            ->map->first();

        $advice = HarborWidgetAiAdvice::whereIn('harbor_id', $harborIds)
            ->orderBy('week_start', 'desc')
            ->get()
            ->groupBy('harbor_id')
            ->map->first();

        $snapshots = HarborWidgetDailySnapshot::whereIn('harbor_id', $harborIds)
            ->orderBy('checked_at', 'desc')
            ->get()
            ->groupBy('harbor_id')
            ->map->first();

        $benchmark = (float) env('HARBOR_WIDGET_CTR_BENCHMARK', 10);

        $data = $partners->map(function (User $partner) use ($settings, $metrics, $advice, $snapshots) {
            return [
                'harbor' => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'email' => $partner->email,
                    'status' => $partner->status,
                ],
                'settings' => $settings->get($partner->id),
                'latest_metric' => $metrics->get($partner->id),
                'latest_advice' => $advice->get($partner->id),
                'latest_snapshot' => $this->formatSnapshot($snapshots->get($partner->id)),
            ];
        });

        return response()->json([
            'benchmark_ctr' => $benchmark,
            'harbors' => $data,
        ]);
    }

    public function weekly(User $harbor, Request $request)
    {
        $this->ensurePartner($harbor);
        $limit = (int) $request->query('limit', 12);

        $metrics = HarborWidgetWeeklyMetric::where('harbor_id', $harbor->id)
            ->orderBy('week_start', 'desc')
            ->limit($limit)
            ->get();

        $adviceByWeek = HarborWidgetAiAdvice::where('harbor_id', $harbor->id)
            ->whereIn('week_start', $metrics->pluck('week_start'))
            ->get()
            ->keyBy(function (HarborWidgetAiAdvice $advice) {
                return $advice->week_start->toDateString();
            });

        $weeks = $metrics->map(function (HarborWidgetWeeklyMetric $metric) use ($adviceByWeek) {
            $weekKey = $metric->week_start->toDateString();
            $advice = $adviceByWeek->get($weekKey);
            return array_merge($metric->toArray(), [
                'advice' => $advice ? $advice->toArray() : null,
            ]);
        });

        return response()->json([
            'harbor' => [
                'id' => $harbor->id,
                'name' => $harbor->name,
                'email' => $harbor->email,
            ],
            'settings' => HarborWidgetSetting::where('harbor_id', $harbor->id)->first(),
            'weeks' => $weeks,
        ]);
    }

    public function snapshots(User $harbor, Request $request)
    {
        $this->ensurePartner($harbor);

        $query = HarborWidgetDailySnapshot::where('harbor_id', $harbor->id);

        if ($request->filled('from')) {
            $query->whereDate('checked_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('checked_at', '<=', $request->query('to'));
        }

        $snapshots = $query->orderBy('checked_at', 'desc')->limit(90)->get();
        $snapshots = $snapshots->map(function (HarborWidgetDailySnapshot $snapshot) {
            return $this->formatSnapshot($snapshot);
        });

        return response()->json([
            'harbor' => [
                'id' => $harbor->id,
                'name' => $harbor->name,
                'email' => $harbor->email,
            ],
            'snapshots' => $snapshots,
        ]);
    }

    public function settings(User $harbor)
    {
        $this->ensurePartner($harbor);

        $settings = HarborWidgetSetting::where('harbor_id', $harbor->id)->first();

        return response()->json([
            'harbor' => [
                'id' => $harbor->id,
                'name' => $harbor->name,
                'email' => $harbor->email,
            ],
            'settings' => $settings,
        ]);
    }

    public function upsertSettings(User $harbor, Request $request)
    {
        $this->ensurePartner($harbor);

        $existing = HarborWidgetSetting::where('harbor_id', $harbor->id)->first();

        $rules = [
            'domain' => ($existing ? 'sometimes' : 'required') . '|string|max:255',
            'widget_version' => 'nullable|string|max:50',
            'placement_default' => 'nullable|string|max:100',
            'widget_selector' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ];

        $validated = $request->validate($rules);

        $settings = $existing ?? new HarborWidgetSetting(['harbor_id' => $harbor->id]);
        $settings->fill($validated);
        $settings->save();

        return response()->json([
            'message' => 'Settings saved',
            'settings' => $settings,
        ]);
    }

    private function ensurePartner(User $harbor): void
    {
        if ($harbor->role !== 'Partner') {
            abort(404, 'Harbor not found.');
        }
    }

    private function formatSnapshot(?HarborWidgetDailySnapshot $snapshot): ?array
    {
        if (!$snapshot) {
            return null;
        }

        $data = $snapshot->toArray();
        $data['desktop_screenshot_url'] = $snapshot->desktop_screenshot_path
            ? Storage::disk('public')->url($snapshot->desktop_screenshot_path)
            : null;
        $data['mobile_screenshot_url'] = $snapshot->mobile_screenshot_path
            ? Storage::disk('public')->url($snapshot->mobile_screenshot_path)
            : null;

        return $data;
    }
}
