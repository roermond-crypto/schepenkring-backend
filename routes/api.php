<?php

use App\Http\Controllers\Api\Admin\ImpersonationController as AdminImpersonationController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\UserLocationController as AdminUserLocationController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\Me\AddressController as MeAddressController;
use App\Http\Controllers\Api\Me\MeController;
use App\Http\Controllers\Api\Me\PasswordController as MePasswordController;
use App\Http\Controllers\Api\Me\PersonalController as MePersonalController;
use App\Http\Controllers\Api\Me\ProfileController as MeProfileController;
use App\Http\Controllers\Api\Me\SecurityController as MeSecurityController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:10,1');
    Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me/profile', [MeProfileController::class, 'update']);
    Route::patch('me/personal', [MePersonalController::class, 'update']);
    Route::patch('me/address', [MeAddressController::class, 'update']);
    Route::patch('me/security', [MeSecurityController::class, 'update']);
    Route::patch('me/password', [MePasswordController::class, 'update']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('users', [AdminUserController::class, 'index']);
    Route::post('users', [AdminUserController::class, 'store']);
    Route::get('users/{id}', [AdminUserController::class, 'show']);
    Route::patch('users/{id}', [AdminUserController::class, 'update']);
    Route::delete('users/{id}', [AdminUserController::class, 'destroy']);

    Route::patch('users/{id}/locations', [AdminUserLocationController::class, 'update']);

    Route::post('impersonate/{userId}', [AdminImpersonationController::class, 'store']);
    Route::post('impersonate/stop', [AdminImpersonationController::class, 'destroy']);
});
