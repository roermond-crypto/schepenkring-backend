<?php

use App\Http\Controllers\Api\Admin\ImpersonationController as AdminImpersonationController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
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
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Tasks\BoardController as TaskBoardController;
use App\Http\Controllers\Api\Tasks\ColumnController as TaskColumnController;
use App\Http\Controllers\Api\Tasks\TaskAutomationController;
use App\Http\Controllers\Api\Tasks\TaskAutomationTemplateController;
use App\Http\Controllers\Api\Tasks\TaskController;
use App\Http\Controllers\Api\Tasks\TaskUserController;
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

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::get('public/users/employees', [TaskUserController::class, 'employees']);

    Route::get('boards', [TaskBoardController::class, 'index']);
    Route::post('columns', [TaskColumnController::class, 'store']);
    Route::put('columns/{id}', [TaskColumnController::class, 'update']);
    Route::delete('columns/{id}', [TaskColumnController::class, 'destroy']);
    Route::post('columns/reorder', [TaskColumnController::class, 'reorder']);

    Route::get('tasks', [TaskController::class, 'index']);
    Route::get('tasks/my', [TaskController::class, 'myTasks']);
    Route::get('tasks/calendar', [TaskController::class, 'calendar']);
    Route::post('tasks', [TaskController::class, 'store']);
    Route::post('tasks/reorder', [TaskController::class, 'reorder']);
    Route::get('tasks/{id}', [TaskController::class, 'show']);
    Route::put('tasks/{id}', [TaskController::class, 'update']);
    Route::delete('tasks/{id}', [TaskController::class, 'destroy']);
    Route::patch('tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::patch('tasks/{id}/reschedule', [TaskController::class, 'reschedule']);
    Route::patch('tasks/{id}/reminder', [TaskController::class, 'scheduleReminder']);
    Route::post('tasks/{id}/remind', [TaskController::class, 'remind']);
    Route::patch('tasks/{id}/accept', [TaskController::class, 'accept']);
    Route::patch('tasks/{id}/reject', [TaskController::class, 'reject']);
    Route::get('tasks/{id}/activities', [TaskController::class, 'activities']);
    Route::post('tasks/{id}/comments', [TaskController::class, 'addComment']);
    Route::post('tasks/{id}/attachments', [TaskController::class, 'uploadAttachment']);
    Route::delete('tasks/{taskId}/attachments/{attachmentId}', [TaskController::class, 'deleteAttachment']);

    Route::get('task-automation-templates', [TaskAutomationTemplateController::class, 'index']);
    Route::post('task-automation-templates', [TaskAutomationTemplateController::class, 'store']);
    Route::get('task-automation-templates/{id}', [TaskAutomationTemplateController::class, 'show']);
    Route::put('task-automation-templates/{id}', [TaskAutomationTemplateController::class, 'update']);
    Route::delete('task-automation-templates/{id}', [TaskAutomationTemplateController::class, 'destroy']);

    Route::get('task-automations', [TaskAutomationController::class, 'index']);
    Route::post('task-automations', [TaskAutomationController::class, 'store']);
    Route::get('task-automations/{id}', [TaskAutomationController::class, 'show']);
    Route::patch('task-automations/{id}', [TaskAutomationController::class, 'update']);
    Route::delete('task-automations/{id}', [TaskAutomationController::class, 'destroy']);
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

    Route::get('audit', [AdminAuditLogController::class, 'index']);
    Route::get('audit/{id}', [AdminAuditLogController::class, 'show']);
});
