<?php

namespace App\Console\Commands;

use App\Models\HarborWidgetDailySnapshot;
use App\Models\HarborWidgetEvent;
use App\Models\HarborWidgetWeeklyMetric;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class HarborWidgetAggregateWeeklyMetrics extends Command
{
    protected $signature = 'harbor:widget-aggregate-weekly {--week_start=}';
    protected $description = 'Aggregate weekly harbor widget metrics';

    public function handle(): int
    {
        $weekStart = $this->resolveWeekStart($this->option('week_start'));
        $weekEnd = $weekStart->copy()->addWeek();
        $benchmark = (float) env('HARBOR_WIDGET_CTR_BENCHMARK', 10);

        $harbors = User::where('role', 'Partner')->where('status', 'Active')->get();
        if ($harbors->isEmpty()) {
            $this->info('No active partner harbors found.');
            return self::SUCCESS;
        }

        foreach ($harbors as $harbor) {
            $impressions = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_impression')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $visible = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_visible')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $clicks = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_click')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $visibleRate = $impressions > 0 ? round(($visible / $impressions) * 100, 2) : 0;
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

            $mobileImpressions = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_impression')
                ->where('device_type', 'mobile')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $mobileClicks = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_click')
                ->where('device_type', 'mobile')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $mobileCtr = $mobileImpressions > 0 ? round(($mobileClicks / $mobileImpressions) * 100, 2) : 0;

            $desktopImpressions = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_impression')
                ->where(function ($query) {
                    $query->whereNull('device_type')->orWhere('device_type', '!=', 'mobile');
                })
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $desktopClicks = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_click')
                ->where(function ($query) {
                    $query->whereNull('device_type')->orWhere('device_type', '!=', 'mobile');
                })
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $desktopCtr = $desktopImpressions > 0 ? round(($desktopClicks / $desktopImpressions) * 100, 2) : 0;

            $avgScroll = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_click')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->avg('scroll_depth');

            $avgTime = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_click')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->avg('time_on_page_before_click');

            $redirectFails = HarborWidgetEvent::where('harbor_id', $harbor->id)
                ->where('event_type', 'harbor_button_redirect_fail')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            $snapshots = HarborWidgetDailySnapshot::where('harbor_id', $harbor->id)
                ->whereBetween('checked_at', [$weekStart, $weekEnd])
                ->get();

            $snapshotFailures = $snapshots->filter(function (HarborWidgetDailySnapshot $snapshot) {
                $errors = $snapshot->console_errors ?? [];
                return !$snapshot->widget_found || !$snapshot->widget_visible || !$snapshot->widget_clickable || !empty($errors);
            })->count();

            $errorCount = $redirectFails + $snapshotFailures;

            $totalSnapshots = $snapshots->count();
            $reliabilityScore = $totalSnapshots > 0
                ? (int) round(100 - (($snapshotFailures / $totalSnapshots) * 100))
                : 0;

            $ctrScore = $benchmark > 0 ? min(100, round(($ctr / $benchmark) * 100)) : 0;
            $conversionScore = (int) round(($ctrScore * 0.6) + ($reliabilityScore * 0.4));

            HarborWidgetWeeklyMetric::updateOrCreate([
                'harbor_id' => $harbor->id,
                'week_start' => $weekStart->toDateString(),
            ], [
                'impressions' => $impressions,
                'visible_rate' => $visibleRate,
                'clicks' => $clicks,
                'ctr' => $ctr,
                'mobile_ctr' => $mobileCtr,
                'desktop_ctr' => $desktopCtr,
                'avg_scroll_before_click' => (int) round($avgScroll ?? 0),
                'avg_time_before_click' => (int) round($avgTime ?? 0),
                'error_count' => $errorCount,
                'reliability_score' => $reliabilityScore,
                'conversion_score' => $conversionScore,
                'computed_at' => now(),
            ]);
        }

        $this->info('Weekly harbor widget metrics updated for week starting ' . $weekStart->toDateString());
        return self::SUCCESS;
    }

    private function resolveWeekStart(?string $input): Carbon
    {
        if ($input) {
            return Carbon::parse($input)->startOfWeek();
        }

        return now()->startOfWeek();
    }
}
