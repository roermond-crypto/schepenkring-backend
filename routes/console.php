<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sentry:sync-issues')->everyTenMinutes();
Schedule::command('app:generate-ai-insights')
    ->dailyAt('02:00')
    ->timezone(config('app.timezone', 'UTC'))
    ->withoutOverlapping();
Schedule::command('social:publish-scheduled')->everyMinute();
