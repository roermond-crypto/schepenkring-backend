<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\SentryIssueService;

class SyncSentryIssues extends Command
{
    protected $signature = 'sentry:sync-issues';
    protected $description = 'Sync Sentry issues into platform_errors table';

    public function handle(SentryIssueService $service): int
    {
        $org = env('SENTRY_ORG');
        $project = env('SENTRY_PROJECT');
        $token = env('SENTRY_AUTH_TOKEN');

        if (!$org || !$project || !$token) {
            $this->warn('Sentry sync skipped: missing SENTRY_ORG / SENTRY_PROJECT / SENTRY_AUTH_TOKEN');
            return self::SUCCESS;
        }

        $url = "https://sentry.io/api/0/projects/{$org}/{$project}/issues/";
        $response = Http::withToken($token)->get($url, [
            'limit' => 100,
            'statsPeriod' => '24h',
        ]);

        if ($response->failed()) {
            $this->error('Sentry sync failed: ' . $response->body());
            return self::FAILURE;
        }

        $issues = $response->json();
        foreach ($issues as $issue) {
            $service->upsertFromWebhook(['data' => ['issue' => $issue]]);
        }

        $this->info('Sentry issues synced: ' . count($issues));
        return self::SUCCESS;
    }
}
