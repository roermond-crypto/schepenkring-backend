<?php

use Illuminate\Support\Facades\Route;

Route::any('{any}', function () {
    return response()->json([
        'message' => 'NauticSecure Backend API. Web access is disabled.',
    ], 404);
})->where('any', '^(?!api(?:/|$)).*');
