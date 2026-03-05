<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:process-automations')->everyMinute();
Schedule::command('tasks:send-reminders')->everyMinute();
Schedule::command('sentry:sync-issues')->everyTenMinutes();
Schedule::command('harbor:widget-snapshot')->dailyAt('02:10');
Schedule::command('harbor:widget-aggregate-weekly')->dailyAt('02:30');
Schedule::command('harbor:widget-ai-advice')->dailyAt('03:00');
Schedule::command('chat:check-sla')->everyFiveMinutes();
Schedule::command('social:publish-scheduled')->everyFiveMinutes();
Schedule::command('social:sync-analytics')->dailyAt('04:00');
Schedule::command('voice:charge-usage')->dailyAt('02:00');
Schedule::command('merchant:reconcile')->dailyAt('02:00');
Schedule::command('interaction:daily-summary')->dailyAt('03:30');

// Harbor schedules
Schedule::command('hiswa:scrape')->dailyAt('01:00');        // Nightly HISWA sync
Schedule::command('harbors:enrich --limit=50')->dailyAt('01:30');  // Enrich new/updated harbors
Schedule::command('harbors:generate-pages --limit=20')->dailyAt('02:30'); // Generate AI pages
Schedule::command('harbors:enrich-third-party --limit=25')->dailyAt('03:00'); // Optional third-party contact enrichment

// ---- PRODUCTION QUEUE WORKER AUTOMATION ----
// Runs the queue worker for up to 55 seconds, triggered every minute by `schedule:run`
Schedule::command('queue:work database --sleep=3 --tries=3 --timeout=180 --max-time=55')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// ---- TEMPORARY IMAGE CLEANUP ----
// Automatically clean up original temporary images older than 1 hour in storage/app/temp
Schedule::exec('find ' . storage_path('app/temp') . ' -type f -mmin +60 -delete')
    ->hourly()
    ->runInBackground();
