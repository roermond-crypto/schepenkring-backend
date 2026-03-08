<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\CopilotActionCatalogController;
use App\Http\Controllers\Api\Admin\CopilotActionController;
use App\Http\Controllers\Api\Admin\CopilotActionPhraseController;
use App\Http\Controllers\Api\Admin\CopilotActionWorkflowController;
use App\Http\Controllers\Api\Admin\ImpersonationController as AdminImpersonationController;
use App\Http\Controllers\Api\Admin\PlatformErrorController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\UserLocationController as AdminUserLocationController;
use App\Http\Controllers\Api\Admin\YachtshiftImportController;
use App\Http\Controllers\Api\AiPipelineController;

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\BidWidgetController;
use App\Http\Controllers\Api\BoatDocumentController;
use App\Http\Controllers\Api\ChecklistTemplateController;
use App\Http\Controllers\Api\ChatConversationController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\ChatWidgetController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\CopilotAuditController;
use App\Http\Controllers\Api\CopilotController;
use App\Http\Controllers\Api\CopilotVoiceSettingsController;
use App\Http\Controllers\Api\ImagePipelineController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadConversionController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LockscreenController;
use App\Http\Controllers\Api\SocialVideoController;
use App\Http\Controllers\Api\Me\AddressController as MeAddressController;
use App\Http\Controllers\Api\Me\MeController;
use App\Http\Controllers\Api\Me\PasswordController as MePasswordController;
use App\Http\Controllers\Api\Me\PersonalController as MePersonalController;
use App\Http\Controllers\Api\Me\ProfileController as MeProfileController;
use App\Http\Controllers\Api\Me\SecurityController as MeSecurityController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicLeadController;
use App\Http\Controllers\Api\PublicConversationMessageController;
use App\Http\Controllers\Api\SentryWebhookController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SignhostController;
use App\Http\Controllers\Api\Tasks\BoardController as TaskBoardController;
use App\Http\Controllers\Api\Tasks\ColumnController as TaskColumnController;
use App\Http\Controllers\Api\Tasks\TaskAutomationController;
use App\Http\Controllers\Api\Tasks\TaskAutomationTemplateController;
use App\Http\Controllers\Api\Tasks\TaskController;
use App\Http\Controllers\Api\Tasks\TaskUserController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\YachtController;

// ──────────────────────────────────────────────────────────
// Public routes (no auth needed for dev/testing)

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

// Yachts
Route::apiResource('yachts', YachtController::class);

// ── CRM Public Chat Widget ──────────
Route::post('public/leads', [PublicLeadController::class, 'store']);
Route::prefix('public/conversations/{conversationId}')->group(function () {
    Route::post('messages', [PublicConversationMessageController::class, 'store']);
    Route::patch('lead', [PublicConversationMessageController::class, 'updateLead']);
});

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
Route::post('yachts/{id}/gallery', [ImagePipelineController::class, 'upload']); // Legacy gallery route

// AI pipeline
Route::post('ai/pipeline-extract', [AiPipelineController::class, 'extractAndEnrich']);
Route::post('ai/generate-description', [AiPipelineController::class, 'generateDescription']);

// Checklists & documents
Route::get('checklists/templates', [ChecklistTemplateController::class, 'index']);
Route::prefix('yachts/{yachtId}/documents')->group(function () {
    Route::get('/', [BoatDocumentController::class, 'index']);
    Route::post('/', [BoatDocumentController::class, 'store']);
    Route::delete('/{id}', [BoatDocumentController::class, 'destroy']);
});

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:10,1');
    Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum');
});

// Public widget (leads, chat, bids)
Route::prefix('public')->group(function () {
    Route::get('locations', [LocationController::class, 'index']);

    Route::post('bids/register', [BidWidgetController::class, 'register']);
    Route::post('bids/verify', [BidWidgetController::class, 'verify']);
    Route::get('bids/{yachtId}/state', [BidWidgetController::class, 'state']);
    Route::post('bids/{yachtId}', [BidWidgetController::class, 'place'])->middleware('bid.session');
});

// Chat widget (public)
Route::post('chat/widget/init', [ChatWidgetController::class, 'init']);
Route::post('chat/conversations', [ChatConversationController::class, 'store']);
Route::post('chat/conversations/{id}/messages', [ChatMessageController::class, 'store']);

// Public analytics
Route::post('analytics/track', [AnalyticsController::class, 'track']);
Route::get('analytics/summary', [AnalyticsController::class, 'summary']);

// Webhooks
Route::post('webhooks/signhost', [WebhookController::class, 'signhost']);
Route::post('sentry/webhook', [SentryWebhookController::class, 'handle']);

// ──────────────────────────────────────────────────────────
// Authenticated routes
// ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Current user & lockscreen
    Route::get('user', function (Request $request) {
        return $request->user();
    });
    Route::post('verify-password', [LockscreenController::class, 'verifyPin']);

    // Account settings
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me/profile', [MeProfileController::class, 'update']);
    Route::patch('me/personal', [MePersonalController::class, 'update']);
    Route::patch('me/address', [MeAddressController::class, 'update']);
    Route::patch('me/security', [MeSecurityController::class, 'update']);
    Route::patch('me/password', [MePasswordController::class, 'update']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // Social Media / Video Automation
    // ================== SOCIAL VIDEO AUTOMATION ==================
    Route::post('/social/schedule', [SocialVideoController::class, 'schedule']);
    Route::get('/social/videos', [SocialVideoController::class, 'listVideos']);
    Route::get('/social/posts', [SocialVideoController::class, 'listPosts']);
    Route::patch('/social/posts/{id}/reschedule', [SocialVideoController::class, 'reschedule']);
    Route::post('/social/posts/{id}/retry', [SocialVideoController::class, 'retry']);
    Route::post('/social/videos/{id}/regenerate', [SocialVideoController::class, 'regenerate']);

    // Audit logs
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/{type}/{id}', [AuditLogController::class, 'forResource']);

    // Copilot
    Route::post('copilot/resolve', [CopilotController::class, 'resolve']);
    Route::post('copilot/track', [CopilotController::class, 'track']);
    Route::get('copilot/audit', [CopilotAuditController::class, 'index']);
    Route::get('copilot/voice-settings', [CopilotVoiceSettingsController::class, 'show']);
    Route::put('copilot/voice-settings', [CopilotVoiceSettingsController::class, 'update']);
    // Leads & conversations
    Route::get('leads', [LeadController::class, 'index']);
    Route::post('leads', [LeadController::class, 'store']);
    Route::get('leads/{id}', [LeadController::class, 'show']);
    Route::patch('leads/{id}', [LeadController::class, 'update']);
    Route::post('leads/{id}/convert-to-client', [LeadConversionController::class, 'store']);
    Route::post('conversations/{conversationId}/messages', [ConversationMessageController::class, 'store']);
    Route::get('conversations/{conversationId}/messages', [ConversationMessageController::class, 'index']);

    // Chat inbox (staff & authenticated users)
    Route::get('chat/conversations', [ChatConversationController::class, 'index']);
    Route::get('chat/conversations/{id}', [ChatConversationController::class, 'show']);
    Route::patch('chat/conversations/{id}', [ChatConversationController::class, 'update']);
    Route::get('chat/conversations/{id}/stream', [ChatConversationController::class, 'stream']);
    Route::post('chat/messages/{id}/thumbs-up', [ChatMessageController::class, 'thumbsUp']);

    // Social video automation (NauticSecure parity)
    Route::post('social/schedule', [SocialVideoController::class, 'schedule']);
    Route::get('social/videos', [SocialVideoController::class, 'listVideos']);
    Route::get('social/posts', [SocialVideoController::class, 'listPosts']);
    Route::patch('social/posts/{id}/reschedule', [SocialVideoController::class, 'reschedule']);
    Route::post('social/posts/{id}/retry', [SocialVideoController::class, 'retry']);
    Route::post('social/videos/{id}/regenerate', [SocialVideoController::class, 'regenerate']);

    // Signhost / contracts
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

    // Tasks
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

    // Legacy admin endpoints (non /admin prefix)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingsController::class, 'index']);
            Route::get('/{key}', [SettingsController::class, 'show']);
            Route::put('/', [SettingsController::class, 'update']);
            Route::post('/bulk', [SettingsController::class, 'bulkUpdate']);
        });
    });
});


// ──────────────────────────────────────────────────────────
// Admin routes
// ──────────────────────────────────────────────────────────
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Users
    Route::get('users', [AdminUserController::class, 'index']);
    Route::post('users', [AdminUserController::class, 'store']);
    Route::get('users/{id}', [AdminUserController::class, 'show']);
    Route::patch('users/{id}', [AdminUserController::class, 'update']);
    Route::delete('users/{id}', [AdminUserController::class, 'destroy']);
    Route::patch('users/{id}/locations', [AdminUserLocationController::class, 'update']);
    
    // Yachts (Admin)
    Route::post('yachts/bulk-import', [YachtshiftImportController::class, 'store']);

    // Impersonation
    Route::post('impersonate/{userId}', [AdminImpersonationController::class, 'store']);
    Route::post('impersonate/stop', [AdminImpersonationController::class, 'destroy']);

    // Audit
    Route::get('audit', [AdminAuditLogController::class, 'index']);
    Route::get('audit/{id}', [AdminAuditLogController::class, 'show']);

    // Copilot admin
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
