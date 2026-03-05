<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YachtController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BidController;

// 1. PUBLIC ROUTES (Anyone can view)
Route::get('yachts', [YachtController::class, 'index']);
Route::get('yachts/{id}', [YachtController::class, 'show']);

// 2. PROTECTED ROUTES (Must be logged in)
Route::middleware('auth:sanctum')->group(function () {
    
    // CUSTOMER ACTIONS
    Route::post('bids/place', [BidController::class, 'placeBid'])
        ->middleware('permission:place bids');

    // EMPLOYEE / ADMIN ACTIONS (Fleet Management)
    Route::middleware('permission:manage yachts')->group(function () {
        Route::post('yachts', [YachtController::class, 'store']);
        Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']);
        Route::apiResource('yachts', YachtController::class)->except(['index', 'show']);
    });

    // BID FINALIZATION (Only those with 'accept bids' permission)
    Route::post('bids/{id}/accept', [BidController::class, 'acceptBid'])
        ->middleware('permission:accept bids');
    Route::post('bids/{id}/decline', [BidController::class, 'declineBid'])
        ->middleware('permission:accept bids');

    // TASK MANAGEMENT
    Route::middleware('permission:manage tasks')->group(function () {
        Route::apiResource('tasks', TaskController::class);
        Route::patch('tasks/{id}/status', [TaskController::class, 'updateStatus']);
    });

    // USER & PERMISSION MANAGEMENT (SuperAdmin only)
    Route::middleware('permission:manage users')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('users/{user}/toggle-permission', [UserController::class, 'togglePermission']);
    });

});