<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\BoatFieldController as AdminBoatFieldController;
use App\Http\Controllers\Api\Admin\BoatFieldMappingController as AdminBoatFieldMappingController;
use App\Http\Controllers\Api\Admin\CopilotActionCatalogController;
use App\Http\Controllers\Api\Admin\CopilotActionController;
use App\Http\Controllers\Api\Admin\CopilotActionPhraseController;
use App\Http\Controllers\Api\Admin\CopilotSuggestionController;
use App\Http\Controllers\Api\Admin\CopilotActionWorkflowController;
use App\Http\Controllers\Api\Admin\HarborController as AdminHarborController;
use App\Http\Controllers\Api\Admin\InsightController as AdminInsightController;
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
use App\Http\Controllers\Api\BoatFormConfigController;
use App\Http\Controllers\Api\ChecklistTemplateController;
use App\Http\Controllers\Api\CatalogAutocompleteController;
use App\Http\Controllers\Api\ChatConversationController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\ChatTranslationController;
use App\Http\Controllers\Api\ChatWidgetController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\CopilotAuditController;
use App\Http\Controllers\Api\CopilotController;
use App\Http\Controllers\Api\CopilotVoiceSettingsController;
use App\Http\Controllers\Api\EmployeeUserController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\FaqKnowledgeController;
use App\Http\Controllers\Api\ImagePipelineController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadConversionController;
use App\Http\Controllers\Api\KnowledgeBrainController;
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
use App\Http\Controllers\Api\TelnyxVoiceWebhookController;
use App\Http\Controllers\Api\Tasks\BoardController as TaskBoardController;
use App\Http\Controllers\Api\Tasks\ColumnController as TaskColumnController;
use App\Http\Controllers\Api\Tasks\TaskAutomationController;
use App\Http\Controllers\Api\Tasks\TaskAutomationTemplateController;
use App\Http\Controllers\Api\Tasks\TaskController;
use App\Http\Controllers\Api\Tasks\TaskUserController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VoiceTranscriptController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WhatsApp360DialogWebhookController;
use App\Http\Controllers\Api\YachtController;
use App\Http\Controllers\Api\YachtDraftController;

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

// ── CRM Public Chat Widget ──────────
Route::post('public/leads', [PublicLeadController::class, 'store']);
Route::prefix('public/conversations/{conversationId}')->group(function () {
    Route::post('messages', [PublicConversationMessageController::class, 'store']);
    Route::post('ask', [PublicConversationMessageController::class, 'ask']);
    Route::patch('lead', [PublicConversationMessageController::class, 'updateLead']);
});

Route::get('yachts/{yachtId}/fields/{fieldName}/history', [\App\Http\Controllers\Api\YachtFieldHistoryController::class, 'show']);
Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']); // Legacy gallery route

// AI pipeline
Route::post('ai/pipeline-extract', [AiPipelineController::class, 'extractAndEnrich']);
Route::post('ai/generate-description', [AiPipelineController::class, 'generateDescription']);
Route::post('ai/suggestions', [AiPipelineController::class, 'getSuggestions']);

// Checklists
Route::get('checklists/templates', [ChecklistTemplateController::class, 'index']);

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:10,1');
    Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum');
});

// Public widget (leads, chat, bids)
Route::prefix('public')->group(function () {
    Route::get('locations', [LocationController::class, 'index']);
    Route::post('chat/translate', [ChatTranslationController::class, 'translatePublic']);

    Route::post('bids/register', [BidWidgetController::class, 'register']);
    Route::post('bids/verify', [BidWidgetController::class, 'verify']);
    Route::get('bids/{yachtId}/state', [BidWidgetController::class, 'state']);
    Route::match(['get', 'post'], 'bids/{yachtId}', [BidWidgetController::class, 'place'])->middleware('bid.session');
    Route::get('boats/{yachtId}/auction', [BidWidgetController::class, 'auction']);
    Route::get('boats/{yachtId}/bids', [BidWidgetController::class, 'bids']);
    Route::post('boats/{yachtId}/bid', [BidWidgetController::class, 'place'])->middleware('bid.session');
    Route::get('locations/{id}/widget-settings', [\App\Http\Controllers\Api\Admin\LocationWidgetSettingsController::class, 'show']);
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
Route::post('webhooks/whatsapp/360dialog', [WhatsApp360DialogWebhookController::class, 'handle']);
Route::post('webhooks/telnyx/voice', [TelnyxVoiceWebhookController::class, 'handle']);
Route::post('sentry/webhook', [SentryWebhookController::class, 'handle']);

// Internal voice gateway callbacks
Route::post('internal/voice/transcript', [VoiceTranscriptController::class, 'store'])
    ->middleware('internal.secret');

// ──────────────────────────────────────────────────────────
// Authenticated routes
// ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('boat-form-config', [BoatFormConfigController::class, 'index']);

    // Yachts
    Route::apiResource('yachts', YachtController::class);

    // Yacht image pipeline
    Route::prefix('yachts/{yachtId}/images')->group(function () {
        Route::post('/upload', [ImagePipelineController::class, 'upload']);
        Route::get('/', [ImagePipelineController::class, 'index']);
        Route::post('/{imageId}/approve', [ImagePipelineController::class, 'approve']);
        Route::post('/{imageId}/delete', [ImagePipelineController::class, 'deleteImage']);
        Route::post('/{imageId}/toggle-keep-original', [ImagePipelineController::class, 'toggleKeepOriginal']);
        Route::post('/approve-all', [ImagePipelineController::class, 'approveAll']);
    });
    Route::get('yachts/{yachtId}/step2-unlocked', [ImagePipelineController::class, 'step2Unlocked']);
    Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']);

    // Yacht drafts
    Route::post('yacht-drafts', [YachtDraftController::class, 'store']);
    Route::get('yacht-drafts/{draftId}', [YachtDraftController::class, 'show']);
    Route::patch('yacht-drafts/{draftId}', [YachtDraftController::class, 'update']);
    Route::post('yacht-drafts/{draftId}/attach-yacht', [YachtDraftController::class, 'attachYacht']);
    Route::post('yacht-drafts/{draftId}/commit', [YachtDraftController::class, 'commit']);

    // Yacht task automation (manual trigger)
    Route::post('yachts/{id}/trigger-automation', function (Illuminate\Http\Request $request, $id) {
        $yacht = \App\Models\Yacht::findOrFail($id);
        $service = app(\App\Services\BoatTaskAutomationService::class);
        $tasks = $service->fireForYacht($yacht, $request->user());
        return response()->json([
            'message' => count($tasks) . ' task(s) created',
            'tasks' => $tasks,
        ]);
    });

    // Yacht documents
    Route::prefix('yachts/{yachtId}/documents')->group(function () {
        Route::get('/', [BoatDocumentController::class, 'index']);
        Route::post('/', [BoatDocumentController::class, 'store']);
        Route::delete('/{id}', [BoatDocumentController::class, 'destroy']);
    });

    // Current user & lockscreen
    Route::get('user', function (Request $request) {
        return $request->user();
    });
    Route::post('verify-password', [LockscreenController::class, 'verifyPin']);

    // Account settings
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me/profile', [MeProfileController::class, 'update']);
    Route::post('me/avatar', [\App\Http\Controllers\Api\Me\AvatarController::class, 'update']);
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
    Route::post('/social/videos/generate', [SocialVideoController::class, 'generate']);
    Route::post('/social/schedule', [SocialVideoController::class, 'schedule']);
    Route::get('/social/videos', [SocialVideoController::class, 'listVideos']);
    Route::get('/social/videos/{id}', [SocialVideoController::class, 'show']);
    Route::get('/social/posts', [SocialVideoController::class, 'listPosts']);
    Route::patch('/social/posts/{id}/reschedule', [SocialVideoController::class, 'reschedule']);
    Route::post('/social/posts/{id}/retry', [SocialVideoController::class, 'retry']);
    Route::post('/social/videos/{id}/regenerate', [SocialVideoController::class, 'regenerate']);
    Route::post('/social/videos/{id}/notify-owner', [SocialVideoController::class, 'notifyOwner']);

    // Audit logs
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/{type}/{id}', [AuditLogController::class, 'forResource']);

    // Copilot
    Route::post('copilot/resolve', [CopilotController::class, 'resolve']);
    Route::post('copilot/track', [CopilotController::class, 'track']);
    Route::post('copilot/feedback', [CopilotController::class, 'feedback']);
    Route::get('copilot/audit', [CopilotAuditController::class, 'index']);
    Route::get('copilot/voice-settings', [CopilotVoiceSettingsController::class, 'show']);
    Route::put('copilot/voice-settings', [CopilotVoiceSettingsController::class, 'update']);

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
        Route::prefix('settings')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\SettingsController::class, 'index']);
            Route::get('/{key}', [\App\Http\Controllers\Api\SettingsController::class, 'show']);
            Route::put('/', [\App\Http\Controllers\Api\SettingsController::class, 'update']);
            Route::post('/bulk', [\App\Http\Controllers\Api\SettingsController::class, 'bulkUpdate']);
        });

        Route::get('audit', [AdminAuditLogController::class, 'index']);
        Route::get('audit/{id}', [AdminAuditLogController::class, 'show']);
    });

    // Leads & conversations
    Route::get('leads', [LeadController::class, 'index']);
    Route::post('leads', [LeadController::class, 'store']);
    Route::get('leads/{id}', [LeadController::class, 'show']);
    Route::patch('leads/{id}', [LeadController::class, 'update']);
    Route::post('leads/{id}/convert-to-client', [LeadConversionController::class, 'store']);
    Route::post('conversations/{conversationId}/messages', [ConversationMessageController::class, 'store']);
    Route::get('conversations/{conversationId}/messages', [ConversationMessageController::class, 'index']);

    // Chat inbox (staff & authenticated users)
    Route::post('chat/translate', [ChatTranslationController::class, 'translate']);
    Route::get('chat/conversations', [ChatConversationController::class, 'index']);
    Route::get('chat/conversations/{id}', [ChatConversationController::class, 'show']);
    Route::patch('chat/conversations/{id}', [ChatConversationController::class, 'update']);
    Route::patch('chat/conversations/{id}/contact', [ChatConversationController::class, 'updateContact']);
    Route::get('chat/conversations/{id}/stream', [ChatConversationController::class, 'stream']);
    Route::post('chat/messages/{id}/thumbs-up', [ChatMessageController::class, 'thumbsUp']);

    // Location FAQ training
    Route::get('faqs', [FaqController::class, 'index']);
    Route::post('faqs', [FaqController::class, 'store']);
    Route::get('faqs/knowledge/documents', [FaqKnowledgeController::class, 'documents']);
    Route::post('faqs/knowledge/documents', [FaqKnowledgeController::class, 'upload']);
    Route::get('faqs/knowledge/items', [FaqKnowledgeController::class, 'items']);
    Route::patch('faqs/knowledge/items/{item}', [FaqKnowledgeController::class, 'review']);
    Route::delete('faqs/knowledge/items/{item}', [FaqKnowledgeController::class, 'destroy']);
    Route::get('faqs/knowledge/analytics', [FaqKnowledgeController::class, 'analytics']);
    Route::get('faqs/knowledge-brain', [KnowledgeBrainController::class, 'show']);
    Route::get('faqs/knowledge-brain/questions', [KnowledgeBrainController::class, 'questions']);
    Route::get('faqs/knowledge-brain/suggestions', [KnowledgeBrainController::class, 'suggestions']);
    Route::post('faqs/knowledge-brain/refresh', [KnowledgeBrainController::class, 'refresh']);
    Route::patch('faqs/knowledge-brain/suggestions/{suggestion}', [KnowledgeBrainController::class, 'review']);
    Route::put('faqs/{faq}', [FaqController::class, 'update']);
    Route::delete('faqs/{faq}', [FaqController::class, 'destroy']);

    // Social video automation (NauticSecure parity)
    Route::post('social/videos/generate', [SocialVideoController::class, 'generate']);
    Route::post('social/schedule', [SocialVideoController::class, 'schedule']);
    Route::get('social/videos', [SocialVideoController::class, 'listVideos']);
    Route::get('social/videos/{id}', [SocialVideoController::class, 'show']);
    Route::get('social/posts', [SocialVideoController::class, 'listPosts']);
    Route::patch('social/posts/{id}/reschedule', [SocialVideoController::class, 'reschedule']);
    Route::post('social/posts/{id}/retry', [SocialVideoController::class, 'retry']);
    Route::post('social/videos/{id}/regenerate', [SocialVideoController::class, 'regenerate']);
    Route::post('social/videos/{id}/notify-owner', [SocialVideoController::class, 'notifyOwner']);

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

    Route::middleware('admin.errors')->prefix('errors')->group(function () {
        Route::get('/', [PlatformErrorController::class, 'index']);
        Route::get('/stats', [PlatformErrorController::class, 'stats']);
        Route::get('/{error}', [PlatformErrorController::class, 'show']);
        Route::post('/{error}/resolve', [PlatformErrorController::class, 'resolve']);
        Route::post('/{error}/ignore', [PlatformErrorController::class, 'ignore']);
        Route::post('/{error}/note', [PlatformErrorController::class, 'note']);
        Route::post('/{error}/assign', [PlatformErrorController::class, 'assign']);
    });
});


// ──────────────────────────────────────────────────────────
// Admin routes
// ──────────────────────────────────────────────────────────
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::post('boats/{yachtId}/auction/start', [AdminBoatAuctionController::class, 'start']);
    Route::post('boats/{yachtId}/auction/end', [AdminBoatAuctionController::class, 'end']);
});

Route::prefix('employee')->middleware(['auth:sanctum', 'role:employee'])->group(function () {
    Route::get('users', [EmployeeUserController::class, 'index']);
    Route::get('users/{id}', [EmployeeUserController::class, 'show']);
    Route::get('clients', [EmployeeUserController::class, 'index']);
    Route::get('clients/{id}', [EmployeeUserController::class, 'show']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{id}', [AdminUserController::class, 'show']);

    Route::get('insights', [AdminInsightController::class, 'index']);
    Route::get('insights/latest', [AdminInsightController::class, 'latest']);
    Route::get('insights/{insight}', [AdminInsightController::class, 'show']);
    Route::post('insights/generate', [AdminInsightController::class, 'generate']);

    // Harbors
    Route::get('harbors', [AdminHarborController::class, 'index']);
    Route::post('harbors', [AdminHarborController::class, 'store']);
    Route::get('harbors/performance', [AdminHarborController::class, 'performance']);
    Route::patch('harbors/{harbor}', [AdminHarborController::class, 'update']);
    Route::delete('harbors/{harbor}', [AdminHarborController::class, 'destroy']);
    Route::get('harbors/{harbor}', [AdminHarborController::class, 'show']);
    Route::get('locations', [AdminHarborController::class, 'index']);
    Route::post('locations', [AdminHarborController::class, 'store']);
    Route::patch('locations/{harbor}', [AdminHarborController::class, 'update']);
    Route::delete('locations/{harbor}', [AdminHarborController::class, 'destroy']);
    Route::get('locations/{harbor}', [AdminHarborController::class, 'show']);

    // Users
    Route::post('users', [AdminUserController::class, 'store']);
    Route::patch('users/{id}', [AdminUserController::class, 'update']);
    Route::delete('users/{id}', [AdminUserController::class, 'destroy']);
    Route::patch('users/{id}/locations', [AdminUserLocationController::class, 'update']);
    Route::get('locations/{id}/widget-settings', [\App\Http\Controllers\Api\Admin\LocationWidgetSettingsController::class, 'show']);
    Route::put('locations/{id}/widget-settings', [\App\Http\Controllers\Api\Admin\LocationWidgetSettingsController::class, 'update']);
    
    // Yachts (Admin)
    Route::post('yachts/bulk-import', [YachtshiftImportController::class, 'store']);
    Route::get('boat-fields', [AdminBoatFieldController::class, 'index']);
    Route::post('boat-fields', [AdminBoatFieldController::class, 'store']);
    Route::post('boat-fields/generate-help', [AdminBoatFieldController::class, 'generateHelp']);
    Route::get('boat-fields/{boatField}', [AdminBoatFieldController::class, 'show']);
    Route::put('boat-fields/{boatField}', [AdminBoatFieldController::class, 'update']);
    Route::delete('boat-fields/{boatField}', [AdminBoatFieldController::class, 'destroy']);
    Route::get('boat-fields/{boatField}/mappings', [AdminBoatFieldMappingController::class, 'index']);
    Route::put('boat-fields/{boatField}/mappings', [AdminBoatFieldMappingController::class, 'update']);

    // Impersonation
    Route::post('impersonate/{userId}', [AdminImpersonationController::class, 'store']);
    Route::post('impersonate/stop', [AdminImpersonationController::class, 'destroy']);

    // Audit
    Route::get('audit', [AdminAuditLogController::class, 'index']);
    Route::get('audit/{id}', [AdminAuditLogController::class, 'show']);
    Route::get('boat-audit', [\App\Http\Controllers\Api\Admin\BoatAuditController::class, 'index']);

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
    Route::get('copilot/suggestions', [CopilotSuggestionController::class, 'index']);
    Route::post('copilot/suggestions/mine', [CopilotSuggestionController::class, 'mine']);
    Route::get('copilot/suggestions/{suggestion}', [CopilotSuggestionController::class, 'show']);
    Route::put('copilot/suggestions/{suggestion}', [CopilotSuggestionController::class, 'update']);
    Route::post('copilot/suggestions/{suggestion}/approve', [CopilotSuggestionController::class, 'approve']);
    Route::post('copilot/suggestions/{suggestion}/disable', [CopilotSuggestionController::class, 'disable']);
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


// crape 3000+ boats
