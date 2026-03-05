<?php

namespace App\Console\Commands;

use App\Mail\HarborWidgetIssueMail;
use App\Models\HarborWidgetDailySnapshot;
use App\Models\HarborWidgetSetting;
use App\Models\User;
use App\Services\NotificationDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class CaptureHarborWidgetSnapshots extends Command
{
    protected $signature = 'harbor:widget-snapshot {--harbor_id=} {--dry-run}';
    protected $description = 'Capture daily harbor widget screenshots and health checks';

    public function handle(NotificationDispatchService $notifier): int
    {
        $settingsQuery = HarborWidgetSetting::where('active', true);
        if ($this->option('harbor_id')) {
            $settingsQuery->where('harbor_id', (int) $this->option('harbor_id'));
        }

        $settings = $settingsQuery->get();
        if ($settings->isEmpty()) {
            $this->info('No active harbor widget settings found.');
            return self::SUCCESS;
        }

        $script = base_path('scripts/harbor_widget_screenshot.mjs');
        if (!file_exists($script)) {
            $this->error('Screenshot script not found at ' . $script);
            return self::FAILURE;
        }

        foreach ($settings as $setting) {
            if (!$setting->domain) {
                $this->warn("Skipping harbor {$setting->harbor_id}: missing domain.");
                continue;
            }

            $url = $this->normalizeUrl($setting->domain);
            if (!$url) {
                $this->warn("Skipping harbor {$setting->harbor_id}: invalid domain.");
                continue;
            }

            $date = now()->format('Y-m-d');
            $baseDir = "harbor-snapshots/{$setting->harbor_id}/{$date}";
            Storage::disk('public')->makeDirectory($baseDir);

            $desktopPath = storage_path("app/public/{$baseDir}/desktop.png");
            $mobilePath = storage_path("app/public/{$baseDir}/mobile.png");

            if ($this->option('dry-run')) {
                $this->info("[Dry-run] Would capture {$url} -> {$baseDir}");
                continue;
            }

            $result = $this->runScreenshotScript($script, $url, $desktopPath, $mobilePath, $setting->widget_selector);

            $snapshot = HarborWidgetDailySnapshot::create([
                'harbor_id' => $setting->harbor_id,
                'domain' => $setting->domain,
                'desktop_screenshot_path' => file_exists($desktopPath) ? "{$baseDir}/desktop.png" : null,
                'mobile_screenshot_path' => file_exists($mobilePath) ? "{$baseDir}/mobile.png" : null,
                'widget_found' => (bool) ($result['widget_found'] ?? false),
                'widget_visible' => (bool) ($result['widget_visible'] ?? false),
                'widget_clickable' => (bool) ($result['widget_clickable'] ?? false),
                'console_errors' => $result['console_errors'] ?? [],
                'load_time_ms' => $result['load_time_ms'] ?? null,
                'checked_at' => now(),
            ]);

            if ($this->hasIssue($snapshot)) {
                $this->notifyAdmins($notifier, $setting, $snapshot);
            }
        }

        return self::SUCCESS;
    }

    private function runScreenshotScript(string $script, string $url, string $desktopPath, string $mobilePath, ?string $selector): array
    {
        $timeout = (int) env('HARBOR_WIDGET_SCREENSHOT_TIMEOUT', 45000);
        $waitMs = (int) env('HARBOR_WIDGET_SCREENSHOT_WAIT_MS', 3000);

        $args = [
            'node',
            $script,
            '--url',
            $url,
            '--desktop',
            $desktopPath,
            '--mobile',
            $mobilePath,
            '--timeout',
            (string) $timeout,
            '--wait',
            (string) $waitMs,
        ];

        if ($selector) {
            $args[] = '--selector';
            $args[] = $selector;
        }

        $process = new Process($args);
        $process->setTimeout(max(90, (int) ceil($timeout / 1000) + 30));
        $process->run();

        if (!$process->isSuccessful()) {
            return [
                'widget_found' => false,
                'widget_visible' => false,
                'widget_clickable' => false,
                'console_errors' => [trim($process->getErrorOutput()) ?: 'Screenshot process failed'],
            ];
        }

        $output = trim($process->getOutput());
        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            return [
                'widget_found' => false,
                'widget_visible' => false,
                'widget_clickable' => false,
                'console_errors' => ['Invalid JSON output from screenshot script'],
            ];
        }

        return $decoded;
    }

    private function normalizeUrl(string $domain): ?string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return null;
        }

        if (!str_starts_with($domain, 'http://') && !str_starts_with($domain, 'https://')) {
            $domain = 'https://' . $domain;
        }

        return $domain;
    }

    private function hasIssue(HarborWidgetDailySnapshot $snapshot): bool
    {
        if (!$snapshot->widget_found || !$snapshot->widget_visible || !$snapshot->widget_clickable) {
            return true;
        }

        $errors = $snapshot->console_errors ?? [];
        return !empty($errors);
    }

    private function notifyAdmins(NotificationDispatchService $notifier, HarborWidgetSetting $setting, HarborWidgetDailySnapshot $snapshot): void
    {
        $harbor = User::find($setting->harbor_id);
        if (!$harbor) {
            return;
        }

        $admins = User::where('role', 'Admin')->where('status', 'Active')->get();
        if ($admins->isEmpty()) {
            return;
        }

        $message = "Widget health check failed for {$harbor->name} ({$setting->domain}).";
        $data = [
            'harbor_id' => $harbor->id,
            'domain' => $setting->domain,
            'snapshot_id' => $snapshot->id,
            'checked_at' => optional($snapshot->checked_at)->toDateTimeString(),
            'widget_found' => $snapshot->widget_found,
            'widget_visible' => $snapshot->widget_visible,
            'widget_clickable' => $snapshot->widget_clickable,
        ];

        foreach ($admins as $admin) {
            $notifier->notifyUser(
                $admin,
                'harbor_widget_issue',
                'Harbor Widget Issue',
                $message,
                $data,
                new HarborWidgetIssueMail($harbor, $snapshot)
            );
        }
    }
}
