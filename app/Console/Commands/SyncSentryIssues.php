<?php

namespace App\Console\Commands;

use App\Services\SentryIssueService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncSentryIssues extends Command
{
    protected $signature = 'sentry:sync-issues';
    protected $description = 'Sync Sentry issues into platform_errors table';

    public function handle(SentryIssueService $service): int
    {
        $org = trim((string) config('services.sentry.org', ''));
        $project = trim((string) config('services.sentry.project', ''));
        $token = trim((string) config('services.sentry.auth_token', ''));

        if ($org === '' || $project === '' || $token === '') {
            $this->warn('Sentry sync skipped: missing SENTRY_ORG / SENTRY_PROJECT / SENTRY_AUTH_TOKEN');
            return self::SUCCESS;
        }

        $url = "https://sentry.io/api/0/projects/{$org}/{$project}/issues/";

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->get($url, [
                    'limit' => 100,
                    'statsPeriod' => '24h',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Sentry sync request failed', [
                'error' => $e->getMessage(),
                'project' => $project,
                'org' => $org,
            ]);
            $this->warn('Sentry sync skipped: request failed');

            return self::SUCCESS;
        }

        $issues = $response->json();

        if ($response->failed()) {
            Log::warning('Sentry sync failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000, '...'),
                'project' => $project,
                'org' => $org,
            ]);
            $this->warn('Sentry sync skipped: API request failed');

            return self::SUCCESS;
        }

        if (! is_array($issues) || Arr::isAssoc($issues)) {
            Log::warning('Sentry sync returned unexpected payload', [
                'project' => $project,
                'org' => $org,
                'payload' => is_scalar($issues) ? $issues : Str::limit(json_encode($issues), 1000, '...'),
            ]);
            $this->warn('Sentry sync skipped: unexpected API payload');

            return self::SUCCESS;
        }

        $synced = 0;
        $skipped = 0;

        foreach ($issues as $issue) {
            if (! is_array($issue)) {
                $skipped++;
                continue;
            }

            try {
                $service->upsertFromWebhook(['data' => ['issue' => $issue]]);
                $synced++;
            } catch (\Throwable $e) {
                $skipped++;
                Log::warning('Sentry issue sync failed', [
                    'issue_id' => $issue['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = 'Sentry issues synced: ' . $synced;
        if ($skipped > 0) {
            $message .= ' (' . $skipped . ' skipped)';
        }

        $this->info($message);

        return self::SUCCESS;
    }
}
