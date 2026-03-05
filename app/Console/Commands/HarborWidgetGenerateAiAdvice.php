<?php

namespace App\Console\Commands;

use App\Mail\HarborWidgetAdviceMail;
use App\Models\HarborWidgetAiAdvice;
use App\Models\HarborWidgetDailySnapshot;
use App\Models\HarborWidgetSetting;
use App\Models\HarborWidgetWeeklyMetric;
use App\Models\User;
use App\Services\HarborWidgetAiService;
use App\Services\NotificationDispatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class HarborWidgetGenerateAiAdvice extends Command
{
    protected $signature = 'harbor:widget-ai-advice {--week_start=} {--force}';
    protected $description = 'Generate AI advice for harbor widget performance';

    public function handle(HarborWidgetAiService $ai, NotificationDispatchService $notifier): int
    {
        $weekStart = $this->resolveWeekStart($this->option('week_start'));
        $benchmark = (float) env('HARBOR_WIDGET_CTR_BENCHMARK', 10);

        $metrics = HarborWidgetWeeklyMetric::where('week_start', $weekStart->toDateString())->get();
        if ($metrics->isEmpty()) {
            $this->info('No weekly metrics found for ' . $weekStart->toDateString());
            return self::SUCCESS;
        }

        foreach ($metrics as $metric) {
            $harbor = User::find($metric->harbor_id);
            if (!$harbor) {
                continue;
            }

            $existing = HarborWidgetAiAdvice::where('harbor_id', $metric->harbor_id)
                ->where('week_start', $weekStart->toDateString())
                ->first();

            if ($existing && !$this->option('force')) {
                continue;
            }

            $setting = HarborWidgetSetting::where('harbor_id', $metric->harbor_id)->first();
            $latestSnapshot = HarborWidgetDailySnapshot::where('harbor_id', $metric->harbor_id)
                ->orderBy('checked_at', 'desc')
                ->first();

            $payload = [
                'harbor_id' => $metric->harbor_id,
                'week_start' => $metric->week_start->toDateString(),
                'impressions' => $metric->impressions,
                'visible_rate' => $metric->visible_rate,
                'clicks' => $metric->clicks,
                'ctr' => $metric->ctr,
                'mobile_ctr' => $metric->mobile_ctr,
                'desktop_ctr' => $metric->desktop_ctr,
                'avg_scroll_before_click' => $metric->avg_scroll_before_click,
                'avg_time_before_click' => $metric->avg_time_before_click,
                'error_count' => $metric->error_count,
                'reliability_score' => $metric->reliability_score,
                'conversion_score' => $metric->conversion_score,
                'benchmark_ctr' => $benchmark,
                'button_position' => $setting?->placement_default,
                'domain' => $setting?->domain,
                'widget_version' => $setting?->widget_version,
                'widget_issue_count' => $this->widgetIssueCount($metric->harbor_id, $weekStart),
                'screenshot_url' => $latestSnapshot && $latestSnapshot->desktop_screenshot_path
                    ? Storage::disk('public')->url($latestSnapshot->desktop_screenshot_path)
                    : null,
            ];

            $result = $ai->generate($payload);

            $advice = $existing ?? new HarborWidgetAiAdvice();
            $advice->harbor_id = $metric->harbor_id;
            $advice->week_start = $weekStart->toDateString();
            $advice->issues = $result['issues'] ?? [];
            $advice->suggestions = $result['suggestions'] ?? [];
            $advice->priority = $result['priority'] ?? 'low';
            $advice->user_message = $result['user_message'] ?? '';
            $advice->save();

            if (!$existing && $metric->ctr < $benchmark) {
                $this->notifyHarbor($notifier, $harbor, $metric, $advice, $benchmark);
            }
        }

        $this->info('Harbor widget AI advice generated for ' . $weekStart->toDateString());
        return self::SUCCESS;
    }

    private function resolveWeekStart(?string $input): Carbon
    {
        if ($input) {
            return Carbon::parse($input)->startOfWeek();
        }

        return now()->startOfWeek();
    }

    private function widgetIssueCount(int $harborId, Carbon $weekStart): int
    {
        $weekEnd = $weekStart->copy()->addWeek();
        $snapshots = HarborWidgetDailySnapshot::where('harbor_id', $harborId)
            ->whereBetween('checked_at', [$weekStart, $weekEnd])
            ->get();

        return $snapshots->filter(function (HarborWidgetDailySnapshot $snapshot) {
            $errors = $snapshot->console_errors ?? [];
            return !$snapshot->widget_found || !$snapshot->widget_visible || !$snapshot->widget_clickable || !empty($errors);
        })->count();
    }

    private function notifyHarbor(NotificationDispatchService $notifier, User $harbor, HarborWidgetWeeklyMetric $metric, HarborWidgetAiAdvice $advice, float $benchmark): void
    {
        $message = $advice->user_message ?: "Your harbor button CTR is {$metric->ctr}%, below the benchmark of {$benchmark}%.";
        $data = [
            'harbor_id' => $harbor->id,
            'week_start' => $metric->week_start->toDateString(),
            'ctr' => $metric->ctr,
            'benchmark' => $benchmark,
            'advice' => $advice->toArray(),
        ];

        $notifier->notifyUser(
            $harbor,
            'harbor_widget_advice',
            'Harbor Button Performance Update',
            $message,
            $data,
            new HarborWidgetAdviceMail($harbor, $metric, $advice, $benchmark)
        );
    }
}
