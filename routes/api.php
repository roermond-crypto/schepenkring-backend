<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Api\Admin\SalesCommandCenterController;
use App\Http\Controllers\Api\Admin\VoiceAgentController;
use App\Http\Controllers\Api\Admin\VoiceCallController;
use App\Http\Controllers\Api\Admin\VoiceNumberController;
use App\Http\Controllers\Api\Admin\EmailTemplateController;
use App\Http\Controllers\Api\Admin\ContractTemplateController;
use App\Http\Controllers\Api\Admin\ContractTypeController;
use App\Http\Controllers\Api\Admin\ContractInstanceController;
use App\Http\Controllers\Api\Admin\BoatFieldController as AdminBoatFieldController;
use App\Http\Controllers\Api\Admin\BoatFieldMappingController as AdminBoatFieldMappingController;
use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
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
use App\Http\Controllers\Api\Admin\BoatAuctionController as AdminBoatAuctionController;
use App\Http\Controllers\Api\Admin\YachtshiftImportController;
use App\Http\Controllers\Api\AiPipelineController;

use App\Http\Controllers\Api\Onboarding\SellerOnboardingController;
use App\Http\Controllers\Api\Onboarding\BuyerVerificationController;
use App\Http\Controllers\Api\Onboarding\OnboardingWebhookController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\EmailVerificationCodeController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\BidWidgetController;
use App\Http\Controllers\Api\BoatDocumentController;
use App\Http\Controllers\Api\BoatFormConfigController;
use App\Http\Controllers\Api\BoatVideoController;
use App\Http\Controllers\Api\BoatVideoSettingController;
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
use App\Http\Controllers\Api\ContractPartyController;
use App\Http\Controllers\Api\EmailTrackingController;
use App\Http\Controllers\Api\EmployeeUserController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\FaqKnowledgeController;
use App\Http\Controllers\Api\ImagePipelineController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadConversionController;
use App\Http\Controllers\Api\KnowledgeBrainController;
use App\Http\Controllers\Api\AiKnowledgeArticleController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LockscreenController;
use App\Http\Controllers\Api\SocialVideoController;
use App\Http\Controllers\Api\VideoPlanController;
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
use App\Http\Controllers\Api\RetellTool\RetellToolActionController;
use App\Http\Controllers\Api\RetellTool\RetellToolContextController;
use App\Http\Controllers\Api\RetellTool\RetellToolHandoffController;
use App\Http\Controllers\Api\RetellWebhookController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SignhostController;
use App\Http\Controllers\Api\TelnyxVoiceWebhookController;
use App\Http\Controllers\Api\Tasks\BoardController as TaskBoardController;
use App\Http\Controllers\Api\Tasks\ColumnController as TaskColumnController;
use App\Http\Controllers\Api\Tasks\TaskAutomationController;
use App\Http\Controllers\Api\Tasks\TaskAutomationRuleController;
use App\Http\Controllers\Api\Tasks\TaskAutomationTemplateController;
use App\Http\Controllers\Api\Tasks\TaskController;
use App\Http\Controllers\Api\Tasks\TaskUserController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VoiceTranscriptController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WhatsApp360DialogWebhookController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\OfferReplyController;
use App\Http\Controllers\Api\OwnerBidController;
use App\Http\Controllers\Api\SellerDashboardController;
use App\Http\Controllers\Api\YachtController;
use App\Http\Controllers\Api\YachtDraftController;
use App\Http\Controllers\Api\WidgetLeadController;
use App\Http\Controllers\Api\PublicBoatController;

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

// ── Location delete approval (public, token-gated) ──────────
Route::prefix('locations/delete')->group(function () {
    Route::get('approve/{token}', [\App\Http\Controllers\Api\LocationDeleteApprovalController::class, 'approve'])
        ->middleware('throttle:5,1');
    Route::get('cancel/{token}', [\App\Http\Controllers\Api\LocationDeleteApprovalController::class, 'cancel'])
        ->middleware('throttle:5,1');
});

// ── Location booking-settings (public alias, no auth required) ──────────────
Route::get('locations/{id}/booking-settings', [\App\Http\Controllers\Api\Admin\HarborController::class, 'bookingSettings']);

// ── Schepenkring Lead Widget (public, no auth) ──────────────
Route::prefix('widget')->group(function () {
    Route::get('context', [WidgetLeadController::class, 'context'])->middleware('throttle:120,1');
    Route::post('plan-viewing', [WidgetLeadController::class, 'planViewing'])->middleware('throttle:10,1');
    Route::post('offer', [WidgetLeadController::class, 'offer'])->middleware('throttle:10,1');
    Route::post('brochure', [WidgetLeadController::class, 'brochure'])->middleware('throttle:10,1');
    Route::post('callback', [WidgetLeadController::class, 'callback'])->middleware('throttle:10,1');
    Route::post('question', [WidgetLeadController::class, 'question'])->middleware('throttle:10,1');
});

// ── Offer reply (public, token-gated — seller responds via email link) ──
Route::prefix('offers')->group(function () {
    Route::get('reply', [\App\Http\Controllers\Api\OfferReplyController::class, 'show'])
        ->middleware('throttle:20,1');
    Route::post('reply', [\App\Http\Controllers\Api\OfferReplyController::class, 'reply'])
        ->middleware('throttle:5,1');
});

// ── Public boat intake (sell your boat form) ─────────────────
Route::prefix('boat-intake')->group(function () {
    Route::post('/', [\App\Http\Controllers\Api\BoatIntakeController::class, 'store'])
        ->middleware('throttle:5,1');
    Route::get('{token}', [\App\Http\Controllers\Api\BoatIntakeController::class, 'showByToken'])
        ->middleware('throttle:30,1');
    Route::post('{token}/photos', [\App\Http\Controllers\Api\BoatIntakeController::class, 'uploadPhotos'])
        ->middleware('throttle:20,1');
    Route::delete('{token}/photos/{photoId}', [\App\Http\Controllers\Api\BoatIntakeController::class, 'deletePhoto'])
        ->middleware('throttle:30,1');
    Route::post('{token}/documents', [\App\Http\Controllers\Api\BoatIntakeController::class, 'uploadDocuments'])
        ->middleware('throttle:20,1');
    Route::post('{token}/resend-confirmation', [\App\Http\Controllers\Api\BoatIntakeController::class, 'resendConfirmation'])
        ->middleware('throttle:3,10'); // max 3 resends per 10 minutes
});

// ── CRM Public Chat Widget ──────────
Route::post('public/leads', [PublicLeadController::class, 'store']);
Route::prefix('public/conversations/{conversationId}')->group(function () {
    Route::get('/', [PublicConversationMessageController::class, 'show']);
    Route::post('messages', [PublicConversationMessageController::class, 'store']);
    Route::post('ask', [PublicConversationMessageController::class, 'ask']);
    Route::patch('lead', [PublicConversationMessageController::class, 'updateLead']);
});

Route::get('yachts/{yachtId}/fields/{fieldName}/history', [\App\Http\Controllers\Api\YachtFieldHistoryController::class, 'show']);
Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']); // Legacy gallery route
Route::get('/video/music-tracks/{slug}/stream', [VideoPlanController::class, 'streamMusicTrack']);

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

    // Password Reset (API JSON version)
    Route::post('forgot-password', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'sendResetLinkEmail'])
        ->middleware('guest');
    Route::post('verify-reset-token', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'verifyToken'])
        ->middleware('guest');
    Route::post('reset-password', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'reset'])
        ->middleware('guest');
});

Route::post('resend-verification', [EmailVerificationCodeController::class, 'resend'])
    ->middleware('throttle:6,1');
Route::post('verify-email', [EmailVerificationCodeController::class, 'verify'])
    ->middleware('throttle:10,1');

// Public widget (leads, chat, bids)
Route::prefix('public')->group(function () {
    Route::get('locations', [LocationController::class, 'index']);
    Route::get('locations/{slug}', [LocationController::class, 'show'])->where('slug', '[a-z0-9\-]+');
    Route::get('locations/{id}/availability', [\App\Http\Controllers\Api\BookingController::class, 'availability']);
    Route::post('bookings', [\App\Http\Controllers\Api\BookingController::class, 'store'])->middleware('auth.optional');
    Route::post('chat/translate', [ChatTranslationController::class, 'translatePublic']);

    Route::post('bids/register', [BidWidgetController::class, 'register']);
    Route::post('bids/verify', [BidWidgetController::class, 'verify']);
    Route::get('bids/{yachtId}/state', [BidWidgetController::class, 'state']);
    Route::match(['get', 'post'], 'bids/{yachtId}', [BidWidgetController::class, 'place'])->middleware('bid.session');
    Route::get('boats/{yachtId}/auction', [BidWidgetController::class, 'auction']);
    Route::get('boats/{yachtId}/bids', [BidWidgetController::class, 'bids']);
    Route::post('boats/{yachtId}/bid', [BidWidgetController::class, 'place'])->middleware('bid.session');
    Route::get('locations/{id}/widget-settings', [\App\Http\Controllers\Api\Admin\LocationWidgetSettingsController::class, 'show']);
    Route::get('locations/{id}/booking-settings', [\App\Http\Controllers\Api\Admin\HarborController::class, 'bookingSettings']);

    // Public boat detail — used by the public boat detail pages and widget context resolution.
    // Accepts both internal DB id and external yachtshift_id.
    Route::get('boats/{id}',  [PublicBoatController::class, 'show']);
    Route::get('yachts/{id}', [PublicBoatController::class, 'show']);

    // Widget boat-context — resolves boat → location for the embed script BFF.
    Route::get('widget/boat-context', [WidgetLeadController::class, 'context'])->middleware('throttle:120,1');
});

// Chat widget (public)
// auth.optional: attempts Sanctum token auth if a Bearer token is present, but
// does NOT reject unauthenticated (guest) requests. This ensures that logged-in
// dashboard users are recognised as themselves instead of "Anonymous" when they
// start or continue a chat conversation.
Route::post('chat/widget/init', [ChatWidgetController::class, 'init']);
Route::post('chat/conversations', [ChatConversationController::class, 'store'])->middleware('auth.optional');
Route::post('chat/conversations/{id}/messages', [ChatMessageController::class, 'store'])->middleware('auth.optional');

// Public analytics
Route::post('analytics/track', [AnalyticsController::class, 'track']);
Route::get('analytics/summary', [AnalyticsController::class, 'summary']);

// Webhooks
Route::post('webhooks/signhost', [WebhookController::class, 'signhost']);
Route::post('webhooks/whatsapp/360dialog', [WhatsApp360DialogWebhookController::class, 'handle']);
Route::post('webhooks/telnyx/voice', [TelnyxVoiceWebhookController::class, 'handle']);
Route::post('webhooks/retell', [RetellWebhookController::class, 'handle']);
Route::post('sentry/webhook', [SentryWebhookController::class, 'handle']);

// Retell agent tool calls — mid-conversation function calls, gated by a
// shared secret (retell.tools middleware) rather than user auth, since
// there is no logged-in user on these requests.
Route::prefix('integrations/retell/tools')->middleware('retell.tools')->group(function () {
    Route::post('users/get-context', [RetellToolContextController::class, 'userContext']);
    Route::post('sellers/get-context', [RetellToolContextController::class, 'sellerContext']);
    Route::post('buyers/get-context', [RetellToolContextController::class, 'buyerContext']);
    Route::post('yachts/get-context', [RetellToolContextController::class, 'yachtContext']);
    Route::post('locations/get-context', [RetellToolContextController::class, 'locationContext']);
    Route::post('deals/get-status', [RetellToolContextController::class, 'dealStatus']);
    Route::post('bids/get-status', [RetellToolContextController::class, 'bidStatus']);
    Route::post('contracts/get-status', [RetellToolContextController::class, 'contractStatus']);
    Route::post('payments/get-status', [RetellToolContextController::class, 'paymentStatus']);
    Route::post('appointments/create', [RetellToolActionController::class, 'createAppointment']);
    Route::post('callbacks/create', [RetellToolActionController::class, 'createCallback']);
    Route::post('onboarding/send-link', [RetellToolActionController::class, 'sendOnboardingLink']);
    Route::post('onboarding/get-status', [RetellToolActionController::class, 'onboardingStatus']);
    Route::post('handoffs/find-destination', [RetellToolHandoffController::class, 'findDestination']);
});

// Internal voice gateway callbacks
Route::post('internal/voice/transcript', [VoiceTranscriptController::class, 'store'])
    ->middleware('internal.secret');

// Campaign email open/click tracking — hit directly by email clients and
// recipients, never authenticated.
Route::get('email/track/{token}.gif', [EmailTrackingController::class, 'pixel'])->name('email.track');
Route::get('email/click/{token}', [EmailTrackingController::class, 'click'])->name('email.click');

// ──────────────────────────────────────────────────────────
// Authenticated routes
// ──────────────────────────────────────────────────────────
// Public media streaming endpoints (HTML <video>/<audio> cannot send Authorization headers reliably).
Route::get('/video-plans/{id}/stream', [VideoPlanController::class, 'streamRenderedPlan']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('boat-form-config', [BoatFormConfigController::class, 'index']);

    // Yachts
    Route::apiResource('yachts', YachtController::class);

    // Yacht image pipeline
    Route::prefix('yachts/{yachtId}/images')->group(function () {
        Route::post('/upload', [ImagePipelineController::class, 'upload']);
        Route::get('/', [ImagePipelineController::class, 'index']);
        Route::post('/reorder', [ImagePipelineController::class, 'reorder']);
        Route::post('/auto-classify', [ImagePipelineController::class, 'autoClassify']);
        Route::post('/{imageId}/approve', [ImagePipelineController::class, 'approve']);
        Route::post('/{imageId}/delete', [ImagePipelineController::class, 'deleteImage']);
        Route::post('/{imageId}/toggle-keep-original', [ImagePipelineController::class, 'toggleKeepOriginal']);
        Route::post('/approve-all', [ImagePipelineController::class, 'approveAll']);
    });
    Route::get('yachts/{yachtId}/step2-unlocked', [ImagePipelineController::class, 'step2Unlocked']);
    Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']);
    Route::post('yachts/{id}/generate-description', [AiPipelineController::class, 'generateDescription']);

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

    // Channel listings (Marktplaats, etc.)
    Route::get('yachts/{id}/channel-listings', [\App\Http\Controllers\Api\YachtChannelListingController::class, 'index']);
    Route::put('yachts/{id}/channel-listings/marktplaats', [\App\Http\Controllers\Api\YachtChannelListingController::class, 'upsertMarktplaats']);
    Route::post('yachts/{id}/channel-listings/marktplaats/{action}', [\App\Http\Controllers\Api\YachtChannelListingController::class, 'actionMarktplaats']);

    // Boat matching (AI assistant)
    Route::post('boats/match', [\App\Http\Controllers\Api\BoatMatchController::class, 'match']);

    // Uploaded yacht videos
    Route::get('yachts/{yachtId}/boat-videos', [BoatVideoController::class, 'index']);
    Route::post('yachts/{yachtId}/boat-videos', [BoatVideoController::class, 'store']);
    Route::delete('boat-videos/{id}', [BoatVideoController::class, 'destroy']);
    Route::post('boat-videos/{id}/publish', [BoatVideoController::class, 'publish']);
    Route::get('yachts/{id}/video-settings', [BoatVideoSettingController::class, 'show']);
    Route::put('yachts/{id}/video-settings', [BoatVideoSettingController::class, 'update']);

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

    // Seller dashboard
    Route::get('dashboard/seller/summary', [SellerDashboardController::class, 'summary']);

    // Owner bids (direct buyer→seller bidding)
    Route::get('owner-bids', [OwnerBidController::class, 'index']);
    Route::post('owner-bids', [OwnerBidController::class, 'store']);
    Route::post('owner-bids/{id}/counter', [OwnerBidController::class, 'counter']);
    Route::post('owner-bids/{id}/accept', [OwnerBidController::class, 'accept']);
    Route::post('owner-bids/{id}/reject', [OwnerBidController::class, 'reject']);
    Route::post('owner-bids/{id}/accept-counter', [OwnerBidController::class, 'acceptCounter']);
    Route::post('owner-bids/{id}/reject-counter', [OwnerBidController::class, 'rejectCounter']);

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('owner-bids', [OwnerBidController::class, 'adminIndex']);
        Route::patch('owner-bids/{id}', [OwnerBidController::class, 'adminUpdate']);
        Route::post('owner-bids/{id}/pause', [OwnerBidController::class, 'pause']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    Route::get('contract-parties', [ContractPartyController::class, 'index']);
    Route::post('contract-parties', [ContractPartyController::class, 'store']);
    Route::patch('contract-parties/{contractParty}', [ContractPartyController::class, 'update']);
    Route::delete('contract-parties/{contractParty}', [ContractPartyController::class, 'destroy']);

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

    // Video Templates & AI Plans
    Route::get('/video-templates', [VideoPlanController::class, 'templates']);
    Route::post('/video-templates', [VideoPlanController::class, 'storeTemplate']);
    Route::put('/video-templates/{id}', [VideoPlanController::class, 'updateTemplate']);
    Route::get('/yachts/{id}/video-plans', [VideoPlanController::class, 'index']);
    Route::post('/yachts/{id}/video-plans', [VideoPlanController::class, 'generate']);
    Route::get('/video-plans/{id}', [VideoPlanController::class, 'show']);
    Route::patch('/video-plans/{id}', [VideoPlanController::class, 'update']);
    Route::post('/video-plans/{id}/approve', [VideoPlanController::class, 'approve']);
    Route::post('/video-plans/{id}/render', [VideoPlanController::class, 'render']);
    Route::post('/video-plans/{id}/retry', [VideoPlanController::class, 'retry']);
    Route::post('/video-plans/{id}/preview', [VideoPlanController::class, 'preview']);
    Route::delete('/video-plans/{id}', [VideoPlanController::class, 'destroy']);
    Route::get('/video/music-tracks', [VideoPlanController::class, 'musicTracks']);
    Route::post('/video/music-tracks', [VideoPlanController::class, 'uploadMusicTrack']);
    Route::delete('/video/music-tracks/{slug}', [VideoPlanController::class, 'deleteMusicTrack']);

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

        Route::prefix('admin')->group(function () {
            Route::get('seller-onboarding-reviews', [\App\Http\Controllers\Api\Admin\SellerOnboardingReviewController::class, 'index']);
            Route::get('seller-onboarding-reviews/{sellerOnboarding}', [\App\Http\Controllers\Api\Admin\SellerOnboardingReviewController::class, 'show']);
            Route::post('seller-onboarding-reviews/{sellerOnboarding}/approve', [\App\Http\Controllers\Api\Admin\SellerOnboardingReviewController::class, 'approve']);
            Route::post('seller-onboarding-reviews/{sellerOnboarding}/reject', [\App\Http\Controllers\Api\Admin\SellerOnboardingReviewController::class, 'reject']);
        });
    });

    // Leads (staff-only, location-scoped inside the controller for employees)
    Route::middleware('role:admin,employee')->group(function () {
        Route::get('leads', [LeadController::class, 'index']);
        Route::post('leads', [LeadController::class, 'store']);
        Route::get('leads/{id}', [LeadController::class, 'show']);
        Route::patch('leads/{id}', [LeadController::class, 'update']);
        Route::post('leads/{id}/convert-to-client', [LeadConversionController::class, 'store']);
    });

    // Conversations
    Route::post('conversations/{conversationId}/messages', [ConversationMessageController::class, 'store']);
    Route::get('conversations/{conversationId}/messages', [ConversationMessageController::class, 'index']);

    // Chat inbox (staff & authenticated users)
    Route::post('chat/translate', [ChatTranslationController::class, 'translate']);
    Route::get('chat/conversations', [ChatConversationController::class, 'index']);
    Route::get('chat/conversations/{id}', [ChatConversationController::class, 'show']);
    Route::patch('chat/conversations/{id}', [ChatConversationController::class, 'update']);
    Route::patch('chat/conversations/{id}/contact', [ChatConversationController::class, 'updateContact']);
    Route::get('chat/conversations/{id}/stream', [ChatConversationController::class, 'stream']);
    Route::get('chat/conversations/{id}/ai-summary', [ChatConversationController::class, 'aiSummary']);
    Route::post('chat/messages/{id}/thumbs-up', [ChatMessageController::class, 'thumbsUp']);

    // Location FAQ training
    Route::get('faqs', [FaqController::class, 'index']);
    Route::post('faqs', [FaqController::class, 'store']);
    Route::post('faqs/bulk', [FaqController::class, 'bulk']);
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

    Route::middleware(['auth:sanctum', 'onboarding.active', 'admin.errors'])->prefix('admin/knowledge-articles')->group(function () {
        Route::get('/', [AiKnowledgeArticleController::class, 'index']);
        Route::post('/', [AiKnowledgeArticleController::class, 'store']);
        Route::post('/translate', [AiKnowledgeArticleController::class, 'translate']);
        Route::post('/seed-starter-brands-models', [AiKnowledgeArticleController::class, 'seedStarterBrandsAndModels']);
        Route::put('/{article}', [AiKnowledgeArticleController::class, 'update']);
        Route::delete('/{article}', [AiKnowledgeArticleController::class, 'destroy']);
    });
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
    Route::post('signhost/refresh-url', [SignhostController::class, 'refreshSignUrl']);
    Route::post('yachts/{yachtId}/contract/generate', [SignhostController::class, 'generateYachtContract']);
    Route::post('yachts/{yachtId}/signhost/create', [SignhostController::class, 'createYachtSignhost']);
    Route::get('yachts/{yachtId}/signhost/status', [SignhostController::class, 'yachtStatus']);
    Route::get('yachts/{yachtId}/signhost/documents', [SignhostController::class, 'yachtDocuments']);
    Route::get('yachts/{yachtId}/signhost/url', [SignhostController::class, 'yachtSignUrl']);
    Route::post('yachts/{yachtId}/signhost/refresh-status', [SignhostController::class, 'refreshYachtSignhostStatus']);
    Route::post('yachts/{yachtId}/signhost/resync', [SignhostController::class, 'resyncYachtSignhost']);

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
    Route::post('task-automation-rules/simulate', [TaskAutomationRuleController::class, 'simulate']);
    Route::get('task-automation-rules/logs', [TaskAutomationRuleController::class, 'logs']);
    Route::post('task-automation-rules/logs/{id}/retry', [TaskAutomationRuleController::class, 'retryLog']);
    Route::get('task-automation-rules', [TaskAutomationRuleController::class, 'index']);
    Route::post('task-automation-rules', [TaskAutomationRuleController::class, 'store']);
    Route::get('task-automation-rules/{id}', [TaskAutomationRuleController::class, 'show']);
    Route::put('task-automation-rules/{id}', [TaskAutomationRuleController::class, 'update']);
    Route::delete('task-automation-rules/{id}', [TaskAutomationRuleController::class, 'destroy']);
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

    // Onboarding
    Route::post('/seller-onboarding/start', [SellerOnboardingController::class, 'start']);
    Route::get('/seller-onboarding/status', [SellerOnboardingController::class, 'status']);
    Route::put('/seller-onboarding/profile', [SellerOnboardingController::class, 'updateProfile']);
    Route::post('/seller-onboarding/payment/session', [SellerOnboardingController::class, 'paymentSession']);
    Route::get('/seller-onboarding/payment/status', [SellerOnboardingController::class, 'paymentStatus']);
    Route::post('/seller-onboarding/contract/generate', [SellerOnboardingController::class, 'generateContract']);
    Route::post('/seller-onboarding/signhost/start', [SellerOnboardingController::class, 'startSignhost']);
    Route::get('/seller-onboarding/verification/redirect', [SellerOnboardingController::class, 'verificationRedirect']);
    Route::get('/seller-onboarding/kyc/questions', [SellerOnboardingController::class, 'kycQuestions']);
    Route::post('/seller-onboarding/kyc/answers', [SellerOnboardingController::class, 'answerKyc']);
    Route::post('/seller-onboarding/submit', [SellerOnboardingController::class, 'submit']);

    Route::post('/buyer-verification/start', [BuyerVerificationController::class, 'start']);
    Route::get('/buyer-verification/status', [BuyerVerificationController::class, 'status']);
    Route::put('/buyer-verification/profile', [BuyerVerificationController::class, 'updateProfile']);
    Route::post('/buyer-verification/signhost/start', [BuyerVerificationController::class, 'startSignhost']);
    Route::get('/buyer-verification/verification/redirect', [BuyerVerificationController::class, 'verificationRedirect']);
    Route::get('/buyer-verification/kyc/questions', [BuyerVerificationController::class, 'kycQuestions']);
    Route::post('/buyer-verification/kyc/answers', [BuyerVerificationController::class, 'answerKyc']);
    Route::post('/buyer-verification/submit', [BuyerVerificationController::class, 'submit']);

    // Issue reporting (async AI analysis)
    Route::post('issues', [IssueController::class, 'store']);

    // Profile setup status — used by frontend to decide which onboarding panel to show
    Route::get('/profile-setup/status', [\App\Http\Controllers\Api\ProfileSetupController::class, 'status']);
    Route::get('/profile-setup/address/search', [\App\Http\Controllers\Api\ProfileSetupController::class, 'search']);
    Route::put('/profile-setup/address', [\App\Http\Controllers\Api\ProfileSetupController::class, 'saveAddress']);
});

// Client onboarding — public quick-register (no auth needed)
Route::post('onboarding/quick-register', [\App\Http\Controllers\Api\Onboarding\ClientOnboardingController::class, 'quickRegister']);

// Client onboarding — authenticated actions
Route::middleware('auth:sanctum')->prefix('onboarding')->group(function () {
    Route::post('ai-draft', [\App\Http\Controllers\Api\Onboarding\ClientOnboardingController::class, 'aiDraft']);
    Route::post('deeplink', [\App\Http\Controllers\Api\Onboarding\ClientOnboardingController::class, 'deeplink']);
    Route::get('thank-you', [\App\Http\Controllers\Api\Onboarding\ClientOnboardingController::class, 'thankYou']);
});

// Onboarding Webhooks
Route::post('/onboarding/webhooks/mollie', [OnboardingWebhookController::class, 'mollie']);
Route::post('/onboarding/webhooks/signhost', [OnboardingWebhookController::class, 'signhost']);


// ──────────────────────────────────────────────────────────
// Admin routes
// ──────────────────────────────────────────────────────────
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::post('boats/{yachtId}/auction/start', [AdminBoatAuctionController::class, 'start']);
    Route::post('boats/{yachtId}/auction/end', [AdminBoatAuctionController::class, 'end']);
});

// Sales Command Center (spec §17) — staff who work leads day to day, not
// admin-only, since location employees need this at least as much as admins.
Route::prefix('admin/sales-command-center')->middleware(['auth:sanctum', 'role:admin,employee'])->group(function () {
    Route::get('/', [SalesCommandCenterController::class, 'index']);
    Route::post('call-now', [SalesCommandCenterController::class, 'callNow']);
    Route::post('schedule-callback', [SalesCommandCenterController::class, 'scheduleCallback']);
    Route::post('mark-outcome', [SalesCommandCenterController::class, 'markOutcome']);
});

// Voice AI admin (spec §18) — agent/campaign/number configuration is
// admin-only (spend caps, calling hours, credentials); call history stays
// admin,employee like the rest of the Sales Command Center.
Route::prefix('admin/voice-ai')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('agents', VoiceAgentController::class)->except(['show']);
    Route::apiResource('campaigns', AdminCampaignController::class);
    Route::post('campaigns/{campaign}/targets', [AdminCampaignController::class, 'addTargets']);
    Route::apiResource('numbers', VoiceNumberController::class)->except(['show']);
});
Route::prefix('admin/voice-ai')->middleware(['auth:sanctum', 'role:admin,employee'])->group(function () {
    Route::get('calls', [VoiceCallController::class, 'index']);
    Route::get('calls/{callSession}', [VoiceCallController::class, 'show']);
    Route::get('analytics', [VoiceCallController::class, 'analytics']);
});

Route::prefix('employee')->middleware(['auth:sanctum', 'role:employee'])->group(function () {
    Route::get('users', [EmployeeUserController::class, 'index']);
    Route::get('users/{id}', [EmployeeUserController::class, 'show']);
    Route::patch('users/{id}', [EmployeeUserController::class, 'update']);
    Route::get('clients', [EmployeeUserController::class, 'index']);
    Route::get('clients/{id}', [EmployeeUserController::class, 'show']);
    Route::patch('clients/{id}', [EmployeeUserController::class, 'update']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{id}', [AdminUserController::class, 'show']);

    Route::get('insights', [AdminInsightController::class, 'index']);
    Route::get('insights/latest', [AdminInsightController::class, 'latest']);
    Route::get('insights/{insight}', [AdminInsightController::class, 'show']);
    Route::post('insights/generate', [AdminInsightController::class, 'generate']);

    // Widget performance (admin)
    Route::get('widget/performance', [WidgetLeadController::class, 'performance']);

    // KYC compliance cases (admin)
    Route::prefix('kyc-cases')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'store']);
        Route::get('{kycCase}', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'show']);
        Route::get('{kycCase}/questions', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'questions']);
        Route::post('{kycCase}/answers', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'saveAnswer']);
        Route::post('{kycCase}/documents', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'uploadDocument']);
        Route::post('{kycCase}/verify-document', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'verifyDocument']);
        Route::patch('{kycCase}', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'update']);
        Route::patch('{kycCase}/status', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'updateStatus']);
        Route::delete('{kycCase}', [\App\Http\Controllers\Api\Admin\KycCaseController::class, 'destroy']);
        Route::get('{kycCase}/pdf', [\App\Http\Controllers\Api\Admin\KycPdfController::class, 'show']);
    });

    // KYC question templates (admin)
    Route::prefix('kyc-questions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\KycQuestionTemplateController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Admin\KycQuestionTemplateController::class, 'store']);
        Route::post('reorder', [\App\Http\Controllers\Api\Admin\KycQuestionTemplateController::class, 'reorder']);
        Route::patch('{question}', [\App\Http\Controllers\Api\Admin\KycQuestionTemplateController::class, 'update']);
        Route::delete('{question}', [\App\Http\Controllers\Api\Admin\KycQuestionTemplateController::class, 'destroy']);
    });

    // Boat intakes (admin)
    Route::get('boat-intakes', [\App\Http\Controllers\Api\BoatIntakeController::class, 'adminIndex']);
    Route::get('boat-intakes/{boatIntake}', [\App\Http\Controllers\Api\BoatIntakeController::class, 'adminShow']);
    Route::post('boat-intakes/{boatIntake}/promote', [\App\Http\Controllers\Api\BoatIntakeController::class, 'promote']);
    // Alias so the frontend can call /convert (promotes intake → unified yacht record)
    Route::post('boat-intakes/{boatIntake}/convert', [\App\Http\Controllers\Api\BoatIntakeController::class, 'promote']);

    // Offers (admin)
    Route::get('offers', [\App\Http\Controllers\Api\Admin\OfferController::class, 'index']);
    Route::post('offers', [\App\Http\Controllers\Api\Admin\OfferController::class, 'store']);
    Route::get('offers/{offer}', [\App\Http\Controllers\Api\Admin\OfferController::class, 'show']);
    Route::patch('offers/{offer}', [\App\Http\Controllers\Api\Admin\OfferController::class, 'update']);
    Route::patch('offers/{offer}/status', [\App\Http\Controllers\Api\Admin\OfferController::class, 'updateStatus']);
    Route::post('offers/{offer}/notify-seller', [\App\Http\Controllers\Api\Admin\OfferController::class, 'notifySeller']);
    Route::delete('offers/{offer}', [\App\Http\Controllers\Api\Admin\OfferController::class, 'destroy']);
    Route::get('yachts/{yacht}/offers', [\App\Http\Controllers\Api\Admin\OfferController::class, 'byYacht']);
    Route::get('sellers/{seller}/offers', [\App\Http\Controllers\Api\Admin\OfferController::class, 'bySeller']);
    Route::post('bids/extract', [\App\Http\Controllers\Api\Admin\BidExtractController::class, 'extract']);

    // Bookings
    Route::get('bookings', [AdminBookingController::class, 'index']);
    Route::get('bookings/{id}', [AdminBookingController::class, 'show']);

    // Harbors
    Route::get('harbors', [AdminHarborController::class, 'index']);
    Route::post('harbors', [AdminHarborController::class, 'store']);
    Route::get('harbors/performance', [AdminHarborController::class, 'performance']);
    Route::patch('harbors/{harbor}', [AdminHarborController::class, 'update']);
    Route::delete('harbors/{harbor}', [AdminHarborController::class, 'destroy']);
    Route::get('harbors/{harbor}', [AdminHarborController::class, 'show']);
    Route::get('locations', [AdminHarborController::class, 'index']);
    Route::post('locations', [AdminHarborController::class, 'store']);
    Route::get('locations/performance', [AdminHarborController::class, 'performance']);
    Route::get('locations/archived', [AdminHarborController::class, 'archived']);
    Route::patch('locations/{harbor}', [AdminHarborController::class, 'update']);
    Route::get('locations/{harbor}', [AdminHarborController::class, 'show']);
    Route::get('locations/{harbor}/impact', [AdminHarborController::class, 'impact']);
    Route::get('locations/{harbor}/stats', [AdminHarborController::class, 'stats']);
    Route::get('locations/{harbor}/timeline', [AdminHarborController::class, 'timeline']);
    Route::get('locations/{harbor}/inbox', [AdminHarborController::class, 'inbox']);
    Route::post('locations/{harbor}/video-media', [AdminHarborController::class, 'uploadVideoMedia']);
    Route::get('locations/{harbor}/booking-settings', [AdminHarborController::class, 'bookingSettings']);
    Route::get('locations/{harbor}/users', [AdminHarborController::class, 'locationUsers']);
    Route::post('locations/{harbor}/users', [AdminHarborController::class, 'addLocationUser']);
    Route::delete('locations/{harbor}/users/{userId}', [AdminHarborController::class, 'removeLocationUser']);
    Route::patch('locations/{harbor}/default-seller', [AdminHarborController::class, 'setDefaultSeller']);
    Route::post('locations/{harbor}/request-delete', [AdminHarborController::class, 'requestDeletion']);
    Route::post('locations/{id}/restore', [AdminHarborController::class, 'restore']);
    Route::delete('locations/{id}/permanent', [AdminHarborController::class, 'permanentDelete']);

    // Users
    Route::post('users', [AdminUserController::class, 'store']);
    Route::patch('users/{id}', [AdminUserController::class, 'update']);
    Route::delete('users/{id}', [AdminUserController::class, 'destroy']);
    Route::patch('users/{id}/locations', [AdminUserLocationController::class, 'update']);
    Route::get('locations/{id}/widget-settings', [\App\Http\Controllers\Api\Admin\LocationWidgetSettingsController::class, 'show']);
    Route::put('locations/{id}/widget-settings', [\App\Http\Controllers\Api\Admin\LocationWidgetSettingsController::class, 'update']);
    Route::get('locations/{id}/bid-settings', [\App\Http\Controllers\Api\Admin\LocationBidSettingsController::class, 'show']);
    Route::put('locations/{id}/bid-settings', [\App\Http\Controllers\Api\Admin\LocationBidSettingsController::class, 'update']);
    
    // YachtShift two-way sync
    Route::post('yachtshift/sync', [\App\Http\Controllers\Api\Admin\YachtShiftSyncController::class, 'trigger']);
    Route::get('yachtshift/sync/status', [\App\Http\Controllers\Api\Admin\YachtShiftSyncController::class, 'status']);
    Route::get('yachtshift/conflicts', [\App\Http\Controllers\Api\Admin\YachtShiftSyncController::class, 'conflicts']);
    Route::get('yachtshift/runs', [\App\Http\Controllers\Api\Admin\YachtShiftSyncController::class, 'runs']);
    Route::post('yachts/{yacht}/publish-yachtshift', [\App\Http\Controllers\Api\Admin\YachtShiftSyncController::class, 'publish']);
    Route::post('yachts/{yacht}/retry-yachtshift-export', [\App\Http\Controllers\Api\Admin\YachtShiftSyncController::class, 'retryExport']);
    Route::post('yachtshift/conflicts/{conflictId}/resolve', [\App\Http\Controllers\Api\Admin\YachtShiftSyncController::class, 'resolveConflict']);

    // Yacht draft AI (selectable matches + autofill)
    Route::get('yachts/draft/{draftId}/ai-matches', [\App\Http\Controllers\Api\Admin\YachtDraftAiController::class, 'aiMatches']);
    Route::post('yachts/draft/{draftId}/select-reference-boat', [\App\Http\Controllers\Api\Admin\YachtDraftAiController::class, 'selectReferenceBoat']);
    Route::post('yachts/draft/{draftId}/ai-autofill', [\App\Http\Controllers\Api\Admin\YachtDraftAiController::class, 'aiAutofill']);
    Route::post('yachts/draft/{draftId}/ai-autofill/apply', [\App\Http\Controllers\Api\Admin\YachtDraftAiController::class, 'applyAiAutofill']);

    // Yachts (Admin) — brochure import + completeness score
    Route::post('yachts/import-brochure', [\App\Http\Controllers\Api\Admin\YachtBrochureImportController::class, 'import']);
    Route::get('yachts/{yacht}/score', [\App\Http\Controllers\Api\Admin\YachtCompletenessController::class, 'show']);
    Route::post('yachts/{yacht}/recalculate-score', [\App\Http\Controllers\Api\Admin\YachtCompletenessController::class, 'recalculate']);
    Route::post('yachts/{yacht}/publish-validated', [\App\Http\Controllers\Api\Admin\YachtCompletenessController::class, 'publish']);
    Route::get('yachts/{yacht}/audit', [\App\Http\Controllers\Api\Admin\YachtCompletenessController::class, 'audit']);

    // Yachts (Admin) — bulk import
    Route::post('yachts/bulk-import', [YachtshiftImportController::class, 'store']);
    Route::get('boat-fields', [AdminBoatFieldController::class, 'index']);
    Route::post('boat-fields', [AdminBoatFieldController::class, 'store']);
    Route::post('boat-fields/generate-help', [AdminBoatFieldController::class, 'generateHelp']);
    Route::post('boat-fields/fill-help-defaults', [AdminBoatFieldController::class, 'fillMissingHelpDefaults']);
    Route::post('boat-fields/generate-help-bulk', [AdminBoatFieldController::class, 'generateMissingHelpBulk']);
    Route::get('boat-fields/{boatField}', [AdminBoatFieldController::class, 'show']);
    Route::put('boat-fields/{boatField}', [AdminBoatFieldController::class, 'update']);
    Route::delete('boat-fields/{boatField}', [AdminBoatFieldController::class, 'destroy']);
    Route::get('boat-fields/{boatField}/mappings', [AdminBoatFieldMappingController::class, 'index']);
    Route::put('boat-fields/{boatField}/mappings', [AdminBoatFieldMappingController::class, 'update']);
    Route::post('boat-fields/{boatField}/mappings/generate-ai', [AdminBoatFieldMappingController::class, 'generateAiSuggestions']);

    // Impersonation
    Route::post('impersonate/{userId}', [AdminImpersonationController::class, 'store']);
    Route::post('impersonate/stop', [AdminImpersonationController::class, 'destroy']);

    // Audit
    Route::get('audit/summary', [AdminAuditLogController::class, 'summary']);
    Route::get('audit', [AdminAuditLogController::class, 'index']);
    Route::get('audit/{id}', [AdminAuditLogController::class, 'show']);
    Route::get('boat-audit', [\App\Http\Controllers\Api\Admin\BoatAuditController::class, 'index']);

    // Issue management (admin)
    Route::get('issues', [IssueController::class, 'index'])->missing(fn () => response()->json(['message' => 'Not found'], 404));
    Route::get('issues/{id}/screenshot', [IssueController::class, 'screenshot'])
        ->middleware('signed')
        ->name('admin.issues.screenshot');
    Route::get('issues/{id}', [IssueController::class, 'show']);
    Route::patch('issues/{id}', [IssueController::class, 'update']);
    Route::post('issues/{id}/retry-ai', [IssueController::class, 'retryAi']);

    // AI Library stats and Pinecone re-index
    Route::get('ai-library/stats', [\App\Http\Controllers\Api\Admin\AiLibraryController::class, 'stats']);
    Route::get('ai-library/qa-comparison', [\App\Http\Controllers\Api\Admin\AiLibraryController::class, 'qaComparison']);
    Route::post('ai-library/reindex', [\App\Http\Controllers\Api\Admin\AiLibraryController::class, 'reIndex']);
    Route::post('ai-library/re-index', [\App\Http\Controllers\Api\Admin\AiLibraryController::class, 'reIndex']);

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

    // Integrations (central credential management)
    Route::apiResource('integrations', IntegrationController::class);
    Route::post('integrations/{id}/send-access-details', [IntegrationController::class, 'sendAccessDetails']);

    // Email templates — static routes MUST be declared before {template} wildcard
    Route::get('email-templates/types', [EmailTemplateController::class, 'types']);
    Route::get('email-templates/tags', [EmailTemplateController::class, 'tags']);
    Route::get('email-templates/sample-data/{type}', [EmailTemplateController::class, 'sampleDataByType']);
    Route::post('email-templates/upload-media', [EmailTemplateController::class, 'uploadMedia']);
    Route::get('email-templates', [EmailTemplateController::class, 'index']);
    Route::post('email-templates', [EmailTemplateController::class, 'store']);
    Route::get('email-templates/{template}', [EmailTemplateController::class, 'show']);
    Route::patch('email-templates/{template}', [EmailTemplateController::class, 'update']);
    Route::delete('email-templates/{template}', [EmailTemplateController::class, 'destroy']);
    Route::post('email-templates/{template}/duplicate', [EmailTemplateController::class, 'duplicate']);
    Route::post('email-templates/{template}/assign-to-location', [EmailTemplateController::class, 'assignToLocation']);
    Route::get('email-templates/{template}/versions', [EmailTemplateController::class, 'versions']);
    Route::post('email-templates/{template}/restore-version', [EmailTemplateController::class, 'restoreVersion']);
    Route::post('email-templates/{template}/preview', [EmailTemplateController::class, 'preview']);
    Route::post('email-templates/{template}/test-send', [EmailTemplateController::class, 'testSend']);
    Route::get('email-templates/{template}/sample-data', [EmailTemplateController::class, 'sampleData']);

    // Contract types (CRUD)
    Route::get('contract-types', [ContractTypeController::class, 'index']);
    Route::post('contract-types', [ContractTypeController::class, 'store']);
    Route::patch('contract-types/{contractType}', [ContractTypeController::class, 'update']);
    Route::delete('contract-types/{contractType}', [ContractTypeController::class, 'destroy']);

    // Contract templates
    Route::get('contract-templates/types', [ContractTemplateController::class, 'types']);
    Route::get('contract-templates/tags', [ContractTemplateController::class, 'tags']);
    Route::get('contract-templates/default-for-location', [ContractTemplateController::class, 'defaultForLocation']);
    Route::get('contract-templates', [ContractTemplateController::class, 'index']);
    Route::post('contract-templates', [ContractTemplateController::class, 'store']);
    Route::get('contract-templates/{template}', [ContractTemplateController::class, 'show']);
    Route::patch('contract-templates/{template}', [ContractTemplateController::class, 'update']);
    Route::delete('contract-templates/{template}', [ContractTemplateController::class, 'destroy']);
    Route::post('contract-templates/{template}/duplicate', [ContractTemplateController::class, 'duplicate']);
    Route::post('contract-templates/{template}/assign-to-location', [ContractTemplateController::class, 'assignToLocation']);
    Route::post('contract-templates/{template}/set-default', [ContractTemplateController::class, 'setDefault']);
    Route::get('contract-templates/{template}/versions', [ContractTemplateController::class, 'versions']);
    Route::post('contract-templates/{template}/restore-version', [ContractTemplateController::class, 'restoreVersion']);
    Route::post('contract-templates/{template}/preview', [ContractTemplateController::class, 'preview']);
    Route::get('contract-templates/{template}/pdf', [ContractTemplateController::class, 'pdf']);

    // Contract instances (per sign request)
    Route::get('contract-instances/{signRequestId}', [ContractInstanceController::class, 'show']);
    Route::put('contract-instances/{signRequestId}', [ContractInstanceController::class, 'update']);
    Route::post('contract-instances/{signRequestId}/preview', [ContractInstanceController::class, 'preview']);
    Route::get('contract-instances/{signRequestId}/tags', [ContractInstanceController::class, 'tags']);
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


// AI Helpdesk — voice sessions via Vonage + OpenAI Realtime
Route::middleware('auth:sanctum')->prefix('helpdesk')->group(function () {
    Route::post('voice/session', [\App\Http\Controllers\Api\HelpdeskController::class, 'voiceSession']);
    Route::post('events', [\App\Http\Controllers\Api\HelpdeskController::class, 'recordEvent']);
    Route::get('sessions', [\App\Http\Controllers\Api\HelpdeskController::class, 'sessions']);
    Route::get('sessions/{id}/transcript', [\App\Http\Controllers\Api\HelpdeskController::class, 'transcript']);
});

// QA Health
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/qa')->group(function () {
    Route::get('health', [\App\Http\Controllers\Api\Admin\QaHealthController::class, 'health']);
});

// Platform Network CRUD
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/platforms')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'store']);
    Route::get('{platform}', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'show']);
    Route::put('{platform}', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'update']);
    Route::patch('{platform}', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'update']);
    Route::delete('{platform}', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'destroy']);
});

// Per-boat platform publications + OpenMarine
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/yachts/{yacht}')->group(function () {
    Route::get('platform-publications', [\App\Http\Controllers\Api\Admin\BoatPlatformPublicationController::class, 'index']);
    Route::put('platform-publications', [\App\Http\Controllers\Api\Admin\BoatPlatformPublicationController::class, 'update']);
    Route::post('platform-publications/{platform}/sync', [\App\Http\Controllers\Api\Admin\BoatPlatformPublicationController::class, 'sync']);
    Route::post('open-marine/generate', [\App\Http\Controllers\Api\Admin\OpenMarineController::class, 'generate']);
});

// Publishing health dashboard widget
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/publishing')->group(function () {
    Route::get('health', [\App\Http\Controllers\Api\Admin\PublishingHealthController::class, 'health']);
});

// Signhost monitoring dashboard
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/signhost')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Admin\SignhostMonitorController::class, 'index']);
    Route::post('{signRequest}/resync', [\App\Http\Controllers\Api\Admin\SignhostMonitorController::class, 'resync']);
});

// crape 3000+ boats
