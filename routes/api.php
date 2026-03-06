<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ImagePipelineController;

// ──────────────────────────────────────────────────────────
// Auth routes
// ──────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// ──────────────────────────────────────────────────────────
// PUBLIC routes (no auth needed for dev/testing)
// ──────────────────────────────────────────────────────────

// Yachts CRUD
Route::apiResource('yachts', \App\Http\Controllers\Api\YachtController::class);

// ── Image Pipeline ──────────
Route::prefix('yachts/{yachtId}/images')->group(function () {
    Route::post('/upload', [ImagePipelineController::class, 'upload']);
    Route::get('/', [ImagePipelineController::class, 'index']);
    Route::post('/{imageId}/approve', [ImagePipelineController::class, 'approve']);
    Route::post('/{imageId}/delete', [ImagePipelineController::class, 'deleteImage']);
    Route::post('/{imageId}/toggle-keep-original', [ImagePipelineController::class, 'toggleKeepOriginal']);
    Route::post('/approve-all', [ImagePipelineController::class, 'approveAll']);
});
Route::get('yachts/{yachtId}/step2-unlocked', [ImagePipelineController::class, 'step2Unlocked']);

// Legacy gallery routes (redirect to pipeline)
Route::post('yachts/{id}/gallery', [ImagePipelineController::class, 'upload']);

// AI pipeline
Route::post('ai/pipeline-extract', [\App\Http\Controllers\Api\AiPipelineController::class, 'extractAndEnrich']);
Route::post('ai/generate-description', [\App\Http\Controllers\Api\AiPipelineController::class, 'generateDescription']);

// ── Checklists & Documents ──────────────
Route::get('checklists/templates', [\App\Http\Controllers\Api\ChecklistTemplateController::class, 'index']);

Route::prefix('yachts/{yachtId}/documents')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\BoatDocumentController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\BoatDocumentController::class, 'store']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\BoatDocumentController::class, 'destroy']);
});

// ──────────────────────────────────────────────────────────
// Authenticated routes
// ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Current user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Lockscreen PIN verification
    Route::post('/verify-password', [\App\Http\Controllers\Api\LockscreenController::class, 'verifyPin']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    });

    // Audit logs
    Route::get('audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);
    Route::get('audit-logs/{type}/{id}', [\App\Http\Controllers\Api\AuditLogController::class, 'forResource']);

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
        Route::prefix('settings')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\SettingsController::class, 'index']);
            Route::get('/{key}', [\App\Http\Controllers\Api\SettingsController::class, 'show']);
            Route::put('/', [\App\Http\Controllers\Api\SettingsController::class, 'update']);
            Route::post('/bulk', [\App\Http\Controllers\Api\SettingsController::class, 'bulkUpdate']);
        });
    });
});
