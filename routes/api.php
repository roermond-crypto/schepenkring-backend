<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ImagePipelineController;
use App\Http\Controllers\Api\Admin\ImpersonationController as AdminImpersonationController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\UserLocationController as AdminUserLocationController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\CopilotAuditController;
use App\Http\Controllers\Api\CopilotController;
use App\Http\Controllers\Api\CopilotVoiceSettingsController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadConversionController;
use App\Http\Controllers\Api\Admin\PlatformErrorController;
use App\Http\Controllers\Api\Me\AddressController as MeAddressController;
use App\Http\Controllers\Api\Me\MeController;
use App\Http\Controllers\Api\Me\PasswordController as MePasswordController;
use App\Http\Controllers\Api\Me\PersonalController as MePersonalController;
use App\Http\Controllers\Api\Me\ProfileController as MeProfileController;
use App\Http\Controllers\Api\Me\SecurityController as MeSecurityController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicLeadController;
use App\Http\Controllers\Api\SentryWebhookController;
use App\Http\Controllers\Api\SignhostController;
use App\Http\Controllers\Api\Tasks\BoardController as TaskBoardController;
use App\Http\Controllers\Api\Tasks\ColumnController as TaskColumnController;
use App\Http\Controllers\Api\Tasks\TaskAutomationController;
use App\Http\Controllers\Api\Tasks\TaskAutomationTemplateController;
use App\Http\Controllers\Api\Tasks\TaskController;
use App\Http\Controllers\Api\Tasks\TaskUserController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\Admin\CopilotActionCatalogController;
use App\Http\Controllers\Api\Admin\CopilotActionController;
use App\Http\Controllers\Api\Admin\CopilotActionPhraseController;
use App\Http\Controllers\Api\Admin\CopilotActionWorkflowController;

// ──────────────────────────────────────────────────────────
// PUBLIC routes (no auth needed for dev/testing)
// Auth will be enforced after merging with GitHub login code
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


Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:10,1');
    Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum');
});

Route::prefix('public')->group(function () {
    Route::post('leads', [PublicLeadController::class, 'store']);
    Route::patch('conversations/{conversationId}/lead', [PublicLeadController::class, 'update']);
    Route::post('conversations/{conversationId}/messages', [ConversationMessageController::class, 'store']);
    Route::post('bids/register', [\App\Http\Controllers\Api\BidWidgetController::class, 'register']);
    Route::post('bids/verify', [\App\Http\Controllers\Api\BidWidgetController::class, 'verify']);
    Route::get('bids/{yachtId}/state', [\App\Http\Controllers\Api\BidWidgetController::class, 'state']);
    Route::post('bids/{yachtId}', [\App\Http\Controllers\Api\BidWidgetController::class, 'place'])
        ->middleware('bid.session');
});

Route::post('analytics/track', [AnalyticsController::class, 'track']);
Route::get('analytics/summary', [AnalyticsController::class, 'summary']);

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

    Route::post('copilot/resolve', [CopilotController::class, 'resolve']);
    Route::post('copilot/track', [CopilotController::class, 'track']);
    Route::get('copilot/audit', [CopilotAuditController::class, 'index']);
    Route::get('copilot/voice-settings', [CopilotVoiceSettingsController::class, 'show']);
    Route::put('copilot/voice-settings', [CopilotVoiceSettingsController::class, 'update']);

    Route::get('leads', [LeadController::class, 'index']);
    Route::get('leads/{id}', [LeadController::class, 'show']);
    Route::patch('leads/{id}', [LeadController::class, 'update']);
    Route::post('leads/{id}/convert-to-client', [LeadConversionController::class, 'store']);

    Route::post('conversations/{conversationId}/messages', [ConversationMessageController::class, 'store']);
    Route::get('conversations/{conversationId}/messages', [ConversationMessageController::class, 'index']);

    Route::post('contracts/generate', [SignhostController::class, 'generateContract']);
    Route::post('signhost/request', [SignhostController::class, 'requestSignhost']);
    Route::post('signhost/resend', [SignhostController::class, 'resend']);
    Route::post('signhost/cancel', [SignhostController::class, 'cancel']);
    Route::get('signhost/status', [SignhostController::class, 'status']);
    Route::get('signhost/documents', [SignhostController::class, 'documents']);

    Route::post('deals/{dealId}/contract/generate', [SignhostController::class, 'generateDealContract']);
    Route::post('deals/{dealId}/signhost/create', [SignhostController::class, 'createDealSignhost']);
    Route::get('deals/{dealId}/signhost/status', [SignhostController::class, 'dealStatus']);
    Route::get('deals/{dealId}/signhost/documents', [SignhostController::class, 'dealDocuments']);
    Route::get('deals/{dealId}/signhost/url', [SignhostController::class, 'dealSignUrl']);

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

Route::post('webhooks/signhost', [WebhookController::class, 'signhost']);
Route::post('sentry/webhook', [SentryWebhookController::class, 'handle']);

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

    Route::get('copilot/action-catalog', [CopilotActionCatalogController::class, 'index']);
    Route::post('copilot/draft', [CopilotActionWorkflowController::class, 'draft']);
    Route::post('copilot/validate', [CopilotActionWorkflowController::class, 'validateAction']);
    Route::post('copilot/execute', [CopilotActionWorkflowController::class, 'execute']);

    Route::get('copilot/actions', [CopilotActionController::class, 'index']);
    Route::post('copilot/actions', [CopilotActionController::class, 'store']);
    Route::get('copilot/actions/{action}', [CopilotActionController::class, 'show']);
    Route::put('copilot/actions/{action}', [CopilotActionController::class, 'update']);
    Route::delete('copilot/actions/{action}', [CopilotActionController::class, 'destroy']);

    Route::get('copilot/phrases', [CopilotActionPhraseController::class, 'index']);
    Route::post('copilot/phrases', [CopilotActionPhraseController::class, 'store']);
    Route::put('copilot/phrases/{phrase}', [CopilotActionPhraseController::class, 'update']);
    Route::delete('copilot/phrases/{phrase}', [CopilotActionPhraseController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'admin.errors'])->prefix('admin/errors')->group(function () {
    Route::get('/', [PlatformErrorController::class, 'index']);
    Route::get('/stats', [PlatformErrorController::class, 'stats']);
    Route::get('/{error}', [PlatformErrorController::class, 'show']);
    Route::post('/{error}/resolve', [PlatformErrorController::class, 'resolve']);
    Route::post('/{error}/ignore', [PlatformErrorController::class, 'ignore']);
    Route::post('/{error}/note', [PlatformErrorController::class, 'note']);
    Route::post('/{error}/assign', [PlatformErrorController::class, 'assign']);
});

});
