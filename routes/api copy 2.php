<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YachtController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\QuickAuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| 🌍 PUBLIC ROUTES  →  Access-Control-Allow-Origin: *
|--------------------------------------------------------------------------
*/
Route::middleware('cors.public')->group(function () {

    // AUTH (public endpoints)
    Route::post('/login', [UserController::class, 'login']);
    Route::post('/register/partner', [QuickAuthController::class, 'registerPartner']);
    Route::post('/register', [QuickAuthController::class, 'registerUser']);

    // ANALYTICS
    Route::post('/analytics/track', [AnalyticsController::class, 'track']);
    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);

    // PUBLIC YACHT DATA
    Route::get('yachts', [YachtController::class, 'index']);
    Route::get('yachts/{id}', [YachtController::class, 'show']);
    Route::get('bids/{id}/history', [BidController::class, 'history']);

    // AI CHAT
    Route::post('/ai/chat', [GeminiController::class, 'chat']);
});


/*
|--------------------------------------------------------------------------
| 🔐 PROTECTED ROUTES  →  Specific origins + credentials (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['cors.private', 'auth:sanctum'])->group(function () {

    // CUSTOMER ACTIONS
    Route::post('bids/place', [BidController::class, 'placeBid']);

    /*
    |--------------------------------------------------------------------------
    | YACHT MANAGEMENT
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage yachts')->group(function () {
        Route::post('yachts', [YachtController::class, 'store']);
        Route::post('yachts/{id}', [YachtController::class, 'update']);
        Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']);
        Route::delete('yachts/{id}', [YachtController::class, 'destroy']);
        Route::delete('/gallery/{id}', [YachtController::class, 'deleteGalleryImage']);
        Route::post('yachts/ai-classify', [YachtController::class, 'classifyImages']);
    });

    /*
    |--------------------------------------------------------------------------
    | BID FINALIZATION
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:accept bids')->group(function () {
        Route::post('bids/{id}/accept', [BidController::class, 'acceptBid']);
        Route::post('bids/{id}/decline', [BidController::class, 'declineBid']);
    });

    /*
    |--------------------------------------------------------------------------
    | TASK MANAGEMENT
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage tasks')->group(function () {
        Route::apiResource('tasks', TaskController::class);
        Route::patch('tasks/{id}/status', [TaskController::class, 'updateStatus']);
    });

    /*
    |--------------------------------------------------------------------------
    | USER MANAGEMENT
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage users')->group(function () {
        Route::get('permissions', [UserController::class, 'getAllPermissions']);
        Route::get('roles', [UserController::class, 'getAllRoles']);
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('users/{user}/toggle-permission', [UserController::class, 'togglePermission']);
    });

    /*
    |--------------------------------------------------------------------------
    | PROFILE
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
});
