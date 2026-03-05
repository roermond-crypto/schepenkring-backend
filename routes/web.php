<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth:sanctum', 'admin.errors'])->group(function () {
    Route::get('/docs', function () {
        return view('docs.swagger');
    });

    Route::get('/docs/openapi.yaml', function () {
        $path = base_path('openapi/openapi.yaml');
        if (!File::exists($path)) {
            abort(404);
        }

        return response()->make(File::get($path), 200, [
            'Content-Type' => 'application/yaml',
        ]);
    });
});
