<?php
// ... rest of your imports and routes ...

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YachtController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskAutomationController;
use App\Http\Controllers\TaskAutomationTemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PartnerAgreementController;
use App\Http\Controllers\PartnerContractController;
use App\Http\Controllers\AuthorizationController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PagePermissionController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ActivityLogController; // Add this
use App\Http\Controllers\NotificationController; // Add this
use App\Http\Controllers\FaqController; // Add this
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\BoatSearchController;
use App\Http\Controllers\BoatAnalysisController;
use App\Http\Controllers\ImageSearchController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\PartnerPublicController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\BoatTypeController;
use App\Http\Controllers\BoatCheckController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WhatsApp360DialogWebhookController;
use App\Http\Controllers\TelnyxVoiceWebhookController;
use App\Http\Controllers\VoiceTranscriptController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\InvoiceDocumentController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\HarborForecastController;
use App\Http\Controllers\CopilotController;
use App\Http\Controllers\CopilotAuditController;
use App\Http\Controllers\CopilotActionController;
use App\Http\Controllers\CopilotActionCatalogController;
use App\Http\Controllers\CopilotActionPhraseController;
use App\Http\Controllers\CopilotActionWorkflowController;
use App\Http\Controllers\CopilotVoiceSettingsController;
use App\Http\Controllers\BoatRecognitionController;
use App\Http\Controllers\ImagePipelineController;
use App\Http\Controllers\Api\CatalogAutocompleteController;
use App\Http\Controllers\PlatformErrorController;
use App\Http\Controllers\SentryWebhookController;
use App\Http\Controllers\UserErrorMessageController;
use App\Http\Controllers\FaqAdminController;
use App\Http\Controllers\FaqSearchController;
use App\Http\Controllers\ChatWidgetController;
use App\Http\Controllers\ChatConversationController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ChatAdapterController;
use App\Http\Controllers\InteractionEventCategoryController;
use App\Http\Controllers\InteractionEventTypeController;
use App\Http\Controllers\InteractionTemplateController;
use App\Http\Controllers\InteractionHubController;
use App\Http\Controllers\HarborWidgetAdminController;
use App\Http\Controllers\HarborWidgetEventController;
use App\Http\Controllers\HarborPerformanceController;
use App\Http\Controllers\HarborFunnelEventController;
use App\Http\Controllers\Api\AutofillRagController;
use App\Http\Controllers\Api\AliasAdminController;
use App\Http\Controllers\BoatLikeController;
use App\Http\Controllers\YachtShiftFeedController;
use App\Http\Controllers\HarborController;
use App\Http\Controllers\HarborPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\BlogTranslationController;
use App\Http\Controllers\InteractionTemplateTranslationController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ChecklistTemplateController;
use App\Http\Controllers\BoatDocumentController;

Route::post('/sync-remaining', [SyncController::class, 'retry']);

// ================== BOAT RECOGNITION ==================
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::post('yachts/recognize-boat', [BoatRecognitionController::class, 'recognize']);
    Route::post('yachts/{id}/generate-embedding', [BoatRecognitionController::class, 'generateEmbedding']);
});

Route::post('/search-by-image', [ImageSearchController::class, 'searchByImage']);

Route::post('/analyze-boat', [BoatAnalysisController::class, 'identify']);

Route::get('/search-boats', [BoatSearchController::class, 'search']);

Route::post('/upload-boat', [ImageUploadController::class, 'upload']);
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/partner-fleet/{token}', [PartnerPublicController::class, 'showFleet']);

// AUTH & REGISTRATION
Route::post('/login', [UserController::class, 'login']);
Route::post('/login/otp/verify', [SecurityController::class, 'verifyLoginOtp']);
Route::post('/login/otp/resend', [SecurityController::class, 'resendLoginOtp']);
Route::post('/verify-email', [UserController::class, 'verifyEmail']);
Route::post('/resend-verification', [UserController::class, 'resendVerificationCode']);
Route::get('/verify-email/{token}', [OnboardingController::class, 'verifyEmailInfo']);
Route::post('/verify-email/{token}', [OnboardingController::class, 'verifyEmailConfirm']);
Route::post('/verify-email/{token}/resend', [OnboardingController::class, 'resendVerification']);
Route::post('/verify-email/{token}/change-email', [OnboardingController::class, 'changeEmail']);
// Route::post('/register', [UserController::class, 'register']);
// Route::post('/register/partner', [UserController::class, 'registerPartner']); // Make sure this is uncommented

// In your routes file (api.php), add this before the protected routes:

// PUBLIC USER ROUTE FOR TASK ASSIGNMENT
Route::get('/public/users/employees', [UserController::class, 'getEmployeesForTasks']);

// Registration
Route::post('/register/partner', [OnboardingController::class, 'registerPartner']);
Route::post('/partner/register', [OnboardingController::class, 'registerPartner']);
Route::post('/register', [OnboardingController::class, 'registerUser']);
// ANALYTICS
Route::post('/analytics/track', [AnalyticsController::class, 'track']);
Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
Route::get('/locales', [LocaleController::class, 'index']);

// PUBLIC YACHT ROUTES
Route::prefix('autocomplete')->group(function () {
    Route::get('brands', [CatalogAutocompleteController::class, 'searchBrands']);
    Route::get('models', [CatalogAutocompleteController::class, 'searchModels']);
    Route::get('types', [CatalogAutocompleteController::class, 'searchBoatTypes']);
});
Route::post('ai/autofill-rag', [AutofillRagController::class, 'autofill']);
Route::get('yachts', [YachtController::class, 'index']);
Route::get('yachts/{id}', [YachtController::class, 'show'])->whereNumber('id');
Route::get('bids/{id}/history', [BidController::class, 'history']);
Route::post('/ai/chat', [GeminiController::class, 'chat']);

// In api.php (temporarily)
Route::post('/test-yacht-update', function(Request $request) {
    try {
        $yacht = \App\Models\Yacht::find(140);
        
        // Test setting a single field
        $yacht->name = $request->input('name', 'Test Name');
        $yacht->save();
        
        return response()->json(['success' => true, 'yacht' => $yacht]);
    } catch (\Exception $e) {
        Log::error('Test error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
    // Put this in the Public Routes section
Route::get('yachts/{id}/available-slots', [BookingController::class, 'getAvailableSlots']);
// Add this line
Route::get('yachts/{id}/available-dates', [BookingController::class, 'getAvailableDates']);
// PROTECTED ROUTES (Must be logged in)
// AI extraction (outside auth for local testing — move inside auth:sanctum for production)
Route::post('ai/extract-boat', [YachtController::class, 'extractFromImages']);
Route::post('ai/pipeline-extract', [\App\Http\Controllers\AiPipelineController::class, 'extractAndEnrich']);
Route::post('ai/generate-description', [\App\Http\Controllers\AiPipelineController::class, 'generateDescription']);

Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    
    // CUSTOMER ACTIONS
    Route::post('bids/place', [BidController::class, 'placeBid']);
    Route::get('my-bids', [BidController::class, 'myBids']);        // Bidder sees their own bids
    Route::get('seller-bids', [BidController::class, 'sellerBids']); // Seller sees bids on their yachts

    // Owner can accept/decline bids on their own yachts (no special permission needed)
    Route::post('owner-bids/{id}/accept', [BidController::class, 'acceptBidAsOwner']);
    Route::post('owner-bids/{id}/decline', [BidController::class, 'declineBidAsOwner']);

    // YACHT MANAGEMENT (This is where your Account Setup will post to)
    Route::middleware('permission:manage yachts')->group(function () {
        Route::post('yachts', [YachtController::class, 'store']);
        // Route::post('yachts/{id}', [YachtController::class, 'update']);
        Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']);
        Route::delete('yachts/{id}', [YachtController::class, 'destroy']);
        Route::delete('/gallery/{id}', [YachtController::class, 'deleteGalleryImage']);
    });
    Route::post('yachts/ai-classify', [YachtController::class, 'classifyImages']);
    Route::post('yachts/{id}', [YachtController::class, 'update']);
    Route::put('yachts/{id}', [YachtController::class, 'update']);

    // ================== IMAGE PIPELINE ==================
    Route::prefix('yachts/{yachtId}/images')->group(function () {
        Route::post('/upload', [ImagePipelineController::class, 'upload']);
        Route::get('/', [ImagePipelineController::class, 'index']);
        Route::post('/{imageId}/approve', [ImagePipelineController::class, 'approve']);
        Route::post('/{imageId}/delete', [ImagePipelineController::class, 'deleteImage']);
        Route::post('/{imageId}/toggle-keep-original', [ImagePipelineController::class, 'toggleKeepOriginal']);
        Route::post('/approve-all', [ImagePipelineController::class, 'approveAll']);
    });
    Route::get('yachts/{yachtId}/step2-unlocked', [ImagePipelineController::class, 'step2Unlocked']);

    // ================== BOAT LIKES ==================
    Route::post('yachts/{id}/like', [BoatLikeController::class, 'like']);
    Route::get('yachts/liked', [BoatLikeController::class, 'index']);

    Route::prefix('partner')->group(function () {
        Route::post('yachts', [YachtController::class, 'store']);
        Route::post('yachts/{id}/gallery', [YachtController::class, 'uploadGallery']);
        Route::post('yachts/ai-classify', [YachtController::class, 'classifyImages']);
    });
    Route::get('my-yachts', [YachtController::class, 'partnerIndex']);

    // BID FINALIZATION
    Route::middleware('permission:accept bids')->group(function () {
        Route::post('bids/{id}/accept', [BidController::class, 'acceptBid']);
        Route::post('bids/{id}/decline', [BidController::class, 'declineBid']);
    });
    Route::delete('bids/{id}', [BidController::class, 'destroy']);
    
    Route::get('bids', [BidController::class, 'index']); 

    // TASK MANAGEMENT
Route::get('/user', [UserController::class, 'currentUser']);
    // // Task routes
    // Route::get('/tasks', [TaskController::class, 'index']);
    // Route::get('/tasks/my', [TaskController::class, 'myTasks']);
    // Route::get('/tasks/calendar', [TaskController::class, 'calendarTasks']);
    // Route::post('/tasks', [TaskController::class, 'store']);
    // Route::get('/tasks/{id}', [TaskController::class, 'show']);
    // Route::put('/tasks/{id}', [TaskController::class, 'update']);
    // Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
    // Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    
    // Admin only - get tasks by user
    // Route::get('/users/{userId}/tasks', [TaskController::class, 'getUserTasks'])
    //     ->middleware('permission:manage tasks');
    
    // User and Yacht routes (for dropdowns)
    Route::get('/users/staff', [UserController::class, 'getStaff']);

    // USER MANAGEMENT
    Route::middleware(['permission:manage users', 'security_level:high'])->group(function () {
        // Route::get('permissions', [UserController::class, 'getAllPermissions']);
        // Route::get('roles', [UserController::class, 'getAllRoles']);
        // Route::apiResource('users', UserController::class);


        // ADD THIS LINE [cite: 148]
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate']);
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('users/{user}/toggle-permission', [UserController::class, 'togglePermission']);
    });

    // PROFILE
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);

    
    // New Authorization Endpoints
    Route::get('user/authorizations/{id}', [AuthorizationController::class, 'getUserPermissions']);
Route::post('user/authorizations/{id}/sync', [AuthorizationController::class, 'syncAuthorizations'])->middleware('security_level:high');    
    // Existing User Management [cite: 71]
    Route::apiResource('users', UserController::class)->middleware(['permission:manage users', 'security_level:high']);

    // Admin Dashboard Summary
    Route::get('/dashboard/summary', [\App\Http\Controllers\DashboardController::class, 'summary']);

// Put this in the Protected Routes section
Route::post('yachts/{id}/book', [BookingController::class, 'storeBooking']);


    // ======================= NOTIFICATION ROUTES =======================
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/', [NotificationController::class, 'deleteAll']);
        Route::delete('/{id}', [NotificationController::class, 'delete']);
    });

    // ======================= ACTIVITY LOG ROUTES =======================
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('/stats', [ActivityLogController::class, 'stats']);
        Route::get('/user/{userId}', [ActivityLogController::class, 'userActivity']);
        Route::get('/my-activity', [ActivityLogController::class, 'myActivity']);
        Route::delete('/clear-old', [ActivityLogController::class, 'clearOldLogs']);
    });
});

Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    // Page permissions routes
    Route::get('/page-permissions', [PagePermissionController::class, 'index']);
    Route::get('/users/{user}/page-permissions', [PagePermissionController::class, 'getUserPermissions']);
    Route::post('/users/{user}/page-permissions/update', [PagePermissionController::class, 'updatePermission'])->middleware('security_level:high');
    Route::post('/users/{user}/page-permissions/bulk-update', [PagePermissionController::class, 'bulkUpdate'])->middleware('security_level:high');
    Route::post('/users/{user}/page-permissions/reset', [PagePermissionController::class, 'resetPermissions'])->middleware('security_level:high');
});

// Blog routes
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::get('/blogs', [BlogController::class, 'index']);
    Route::post('/blogs', [BlogController::class, 'store']);
    Route::get('/blogs/{id}', [BlogController::class, 'show']);
    Route::put('/blogs/{id}', [BlogController::class, 'update']);
    Route::delete('/blogs/{id}', [BlogController::class, 'destroy']);
    Route::get('/blogs/slug/{slug}', [BlogController::class, 'showBySlug']);
    Route::get('/blogs/featured', [BlogController::class, 'featured']);
});

// Public blog routes (for reading)
Route::get('/public/blogs', [BlogController::class, 'index']);
Route::get('/public/blogs/{id}', [BlogController::class, 'show']);
Route::get('/public/blogs/slug/{slug}', [BlogController::class, 'showBySlug']);
Route::get('/public/blogs/featured', [BlogController::class, 'featured']);
Route::post('/public/blogs/{id}/view', [BlogController::class, 'incrementViews']); // Add this


// Add to your existing routes
// Public FAQ routes
Route::get('/faqs', [FaqController::class, 'index']);
Route::get('/faqs/{id}', [FaqController::class, 'show']);
Route::post('/faqs/ask-gemini', [FaqController::class, 'askGemini']);
Route::get('/faqs/stats', [FaqController::class, 'stats']);
Route::post('/faqs/{id}/rate-helpful', [FaqController::class, 'rateHelpful']);
Route::post('/faqs/{id}/rate-not-helpful', [FaqController::class, 'rateNotHelpful']);

// New FAQ search + translations (semantic search)
Route::post('/faq/search', [FaqSearchController::class, 'search']);
Route::get('/faq/translations/{translationId}', [FaqSearchController::class, 'showTranslation']);
Route::get('/faq/by-slug/{slug}', [FaqSearchController::class, 'showBySlug']);

// Chat widget + public inbound
Route::post('/chat/widget/init', [ChatWidgetController::class, 'init']);
Route::post('/chat/conversations', [ChatConversationController::class, 'store']);
Route::post('/chat/conversations/{id}/messages', [ChatMessageController::class, 'store']);
Route::post('/chat/adapters/whatsapp/inbound', [ChatAdapterController::class, 'whatsappInbound']);
Route::post('/chat/adapters/email/inbound', [ChatAdapterController::class, 'emailInbound']);

// Onboarding (authenticated)
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::get('/onboarding/status', [OnboardingController::class, 'onboardingStatus'])->name('onboarding.status');
    Route::get('/partner/agreement', [PartnerAgreementController::class, 'show'])->name('onboarding.agreement.show');
    Route::post('/partner/agreement', [PartnerAgreementController::class, 'accept'])->name('onboarding.agreement.accept');
    Route::post('/partner/contract', [PartnerContractController::class, 'create'])->name('onboarding.contract.create');
    Route::get('/partner/contract', [PartnerContractController::class, 'status'])->name('onboarding.contract.status');
});

// Protected FAQ routes (Admin only)
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::post('/faqs', [FaqController::class, 'store']);
    Route::put('/faqs/{id}', [FaqController::class, 'update']);
    Route::delete('/faqs/{id}', [FaqController::class, 'destroy']);
    Route::post('/faqs/train-gemini', [FaqController::class, 'trainGemini']);
    Route::get('/faqs/training-status', [FaqController::class, 'getTrainingStatus']);
});

// Chat inbox (staff & authenticated users)
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::get('/chat/conversations', [ChatConversationController::class, 'index']);
    Route::get('/chat/conversations/{id}', [ChatConversationController::class, 'show']);
    Route::patch('/chat/conversations/{id}', [ChatConversationController::class, 'update']);
    Route::get('/chat/conversations/{id}/stream', [ChatConversationController::class, 'stream']);
    Route::post('/chat/messages/{id}/thumbs-up', [ChatMessageController::class, 'thumbsUp']);
});

// FAQ admin tools (new multilingual system)
Route::middleware(['auth:sanctum', 'onboarding.active', 'admin.errors'])->group(function () {
    Route::get('/admin/faq/translations', [FaqAdminController::class, 'index']);
    Route::post('/admin/faq/import', [FaqAdminController::class, 'import']);
    Route::post('/admin/faq/index', [FaqAdminController::class, 'reindex']);
    Route::post('/admin/faq/long-descriptions', [FaqAdminController::class, 'generateLongDescriptions']);
    Route::post('/admin/faq/translate', [FaqAdminController::class, 'translate']);
    Route::post('/admin/faq/translations/{translationId}/approve', [FaqAdminController::class, 'approveTranslation']);
    Route::get('/admin/blog-translations', [BlogTranslationController::class, 'index']);
    Route::post('/admin/blog-translations/ai-generate', [BlogTranslationController::class, 'generate']);
    Route::patch('/admin/blog-translations/{translation}', [BlogTranslationController::class, 'update']);
    Route::post('/admin/blog-translations/{translation}/approve', [BlogTranslationController::class, 'approve']);
    Route::get('/admin/interaction-template-translations', [InteractionTemplateTranslationController::class, 'index']);
    Route::post('/admin/interaction-template-translations/ai-generate', [InteractionTemplateTranslationController::class, 'generate']);
    Route::patch('/admin/interaction-template-translations/{translation}', [InteractionTemplateTranslationController::class, 'update']);
    Route::post('/admin/interaction-template-translations/{translation}/approve', [InteractionTemplateTranslationController::class, 'approve']);
});

Route::get('/faqs/test-gemini', function () {
    $controller = new FaqController();

    $request = new \Illuminate\Http\Request([
        'question' => 'Hello, what yachts are available?'
    ]);

    return $controller->askGemini($request);
});
// Temporary testing route (no auth)
Route::post('/faqs/test-dummy', [FaqController::class, 'storeDummy']);

Route::delete('/boats/{filename}', [BoatAnalysisController::class, 'destroy']);
// Audit log access (admin only)
Route::middleware(['auth:sanctum', 'onboarding.active', 'admin.only'])->prefix('system-logs')->group(function () {
    Route::get('/', [SystemLogController::class, 'index']);
    Route::get('/summary', [SystemLogController::class, 'summary']);
    Route::get('/export', [SystemLogController::class, 'export']);
    Route::get('/health', [SystemLogController::class, 'health']);
    Route::get('/{id}', [SystemLogController::class, 'show']);
    Route::middleware('throttle:1,1440')->delete('/cleanup', [SystemLogController::class, 'cleanup']);
});


// routes/api.php

use App\Http\Controllers\PartnerUserController;

// Public routes (if any)...

// Protected routes (must be logged in)
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {

    // ================== PARTNER USER MANAGEMENT ==================
    Route::prefix('partner')->group(function () {
        Route::get('/users', [PartnerUserController::class, 'index']);
        Route::post('/users', [PartnerUserController::class, 'store']);
        Route::get('/users/{id}', [PartnerUserController::class, 'show']);
        Route::put('/users/{id}', [PartnerUserController::class, 'update']);
        Route::delete('/users/{id}', [PartnerUserController::class, 'destroy']);
    });

    // ================== PAGE PERMISSIONS ==================
    Route::get('/users/{user}/page-permissions', [PagePermissionController::class, 'getUserPermissions']);
    Route::post('/users/{user}/page-permissions/update', [PagePermissionController::class, 'updatePermission'])->middleware('security_level:high');
    Route::post('/users/{user}/page-permissions/bulk-update', [PagePermissionController::class, 'bulkUpdate'])->middleware('security_level:high');
    Route::post('/users/{user}/page-permissions/reset', [PagePermissionController::class, 'resetPermissions'])->middleware('security_level:high');

//     Route::get('inspections/boat/{boatId}', [InspectionController::class, 'showForBoat']);
// Route::put('inspections/{inspectionId}/answers/{answerId}', [InspectionController::class, 'updateAnswer']);
});

Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    // ... existing routes (bids, tasks, etc.) ...

    // ================== BOAT TYPES ==================
    Route::get('/boat-types', [BoatTypeController::class, 'index']);

    // ================== CHECKLIST QUESTIONS (read-only for all authenticated) ==================
    Route::get('/boat-checks', [BoatCheckController::class, 'index']);
    Route::get('/boat-checks/{id}', [BoatCheckController::class, 'show']);

    // ================== ALIAS ADMIN (AI MAINTENANCE) ==================
    Route::prefix('admin/aliases')->group(function () {
        Route::get('brands', [AliasAdminController::class, 'getPendingBrands']);
        Route::post('brands/{id}/map', [AliasAdminController::class, 'mapBrand']);
        
        Route::get('models', [AliasAdminController::class, 'getPendingModels']);
        Route::post('models/{id}/map', [AliasAdminController::class, 'mapModel']);
        
        Route::post('{type}/{id}/discard', [AliasAdminController::class, 'discardAlias']);
    });

    // ================== INSPECTIONS ==================
    Route::post('/inspections', [InspectionController::class, 'store']);
    Route::post('/inspections/{id}/answers', [InspectionController::class, 'storeAnswers']);
    Route::get('/inspections/boat/{boatId}', [InspectionController::class, 'showForBoat']);
    Route::put('/inspections/{inspectionId}/answers/{answerId}', [InspectionController::class, 'updateAnswer']);

    // ================== AI INSPECTION ==================
    Route::post('/inspections/{id}/ai-analyze', [\App\Http\Controllers\AiInspectionController::class, 'analyze']);

    // ================== INSPECTION REPORTS ==================
    Route::get('/yachts/{id}/inspection-report', [\App\Http\Controllers\InspectionReportController::class, 'show']);
    Route::get('/yachts/{id}/inspection-report/pdf', [\App\Http\Controllers\InspectionReportController::class, 'downloadPdf']);

    // ================== ADVERTISING CHANNELS ==================
    Route::get('/advertising-channels', [\App\Http\Controllers\AdvertisingController::class, 'index']);
    Route::post('/yachts/{id}/syndicate', [\App\Http\Controllers\AdvertisingController::class, 'syndicate']);

    // ================== VIDEO GENERATION ==================
    Route::get('/video/music-tracks', [\App\Http\Controllers\VideoController::class, 'musicTracks']);
    Route::post('/yachts/{id}/generate-video', [\App\Http\Controllers\VideoController::class, 'generate']);
    Route::get('/video-jobs/{id}', [\App\Http\Controllers\VideoController::class, 'status']);
    Route::get('/yachts/{id}/videos', [\App\Http\Controllers\VideoController::class, 'list']);

    // ================== SOCIAL VIDEO AUTOMATION ==================
    Route::post('/social/schedule', [\App\Http\Controllers\SocialVideoController::class, 'schedule']);
    Route::get('/social/videos', [\App\Http\Controllers\SocialVideoController::class, 'listVideos']);
    Route::get('/social/posts', [\App\Http\Controllers\SocialVideoController::class, 'listPosts']);
    Route::patch('/social/posts/{id}/reschedule', [\App\Http\Controllers\SocialVideoController::class, 'reschedule']);
    Route::post('/social/posts/{id}/retry', [\App\Http\Controllers\SocialVideoController::class, 'retry']);
    Route::post('/social/videos/{id}/regenerate', [\App\Http\Controllers\SocialVideoController::class, 'regenerate']);

    // ================== BOAT VIDEO & SOCIAL ==================
    Route::get('/yachts/{id}/boat-videos', [\App\Http\Controllers\Api\BoatVideoController::class, 'index']);
    Route::post('/yachts/{id}/boat-videos', [\App\Http\Controllers\Api\BoatVideoController::class, 'upload']);
    Route::delete('/boat-videos/{id}', [\App\Http\Controllers\Api\BoatVideoController::class, 'destroy']);
    Route::post('/boat-videos/{id}/ai-caption', [\App\Http\Controllers\Api\BoatVideoController::class, 'generateAiCaption']);
    Route::post('/boat-videos/{id}/publish', [\App\Http\Controllers\Api\BoatVideoController::class, 'publish']);

    Route::get('/yachts/{id}/video-settings', [\App\Http\Controllers\Api\BoatVideoSettingController::class, 'show']);
    Route::put('/yachts/{id}/video-settings', [\App\Http\Controllers\Api\BoatVideoSettingController::class, 'update']);

    // ================== CHECKLIST SYSTEM ==================
    Route::get('/checklists/templates', [ChecklistTemplateController::class, 'index']);
    Route::post('/yachts/{yachtId}/documents', [BoatDocumentController::class, 'store']);
    Route::get('/yachts/{yachtId}/documents', [BoatDocumentController::class, 'index']);
    Route::delete('/yachts/{yachtId}/documents/{id}', [BoatDocumentController::class, 'destroy']);

    // ... rest of your protected routes ...
});

// ================== CHECKLIST MANAGEMENT (admin only) ==================
Route::middleware(['auth:sanctum', 'onboarding.active', 'permission:manage checklist questions'])->group(function () {
    Route::post('/boat-checks', [BoatCheckController::class, 'store']);
    Route::put('/boat-checks/{id}', [BoatCheckController::class, 'update']);
    Route::delete('/boat-checks/{id}', [BoatCheckController::class, 'destroy']);
});
Route::post('/register/seller', [PartnerUserController::class, 'registerSeller']);

// use App\Http\Controllers\PartnerTaskController;
// Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
//     // ... existing routes ...

//     // Accept/Reject tasks (for employees)
//     Route::patch('/tasks/{id}/accept', [TaskController::class, 'acceptTask']);
//     Route::patch('/tasks/{id}/reject', [TaskController::class, 'rejectTask']);

// });

// Public (for admin task assignment – returns all employees)
Route::get('/public/users/employees', [UserController::class, 'getEmployeesForTasks']);

// Protected routes
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    // Current user
    Route::get('/user', [UserController::class, 'currentUser']);

    // Boards & Columns
    Route::get('/boards', [\App\Http\Controllers\BoardController::class, 'index']);
    Route::post('/columns', [\App\Http\Controllers\ColumnController::class, 'store']);
    Route::put('/columns/{id}', [\App\Http\Controllers\ColumnController::class, 'update']);
    Route::delete('/columns/{id}', [\App\Http\Controllers\ColumnController::class, 'destroy']);
    Route::post('/columns/reorder', [\App\Http\Controllers\ColumnController::class, 'reorder']);

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/my', [TaskController::class, 'myTasks']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::post('/tasks/reorder', [TaskController::class, 'reorder']);
    Route::get('/tasks/{id}', [TaskController::class, 'show']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::patch('/tasks/{id}/reschedule', [TaskController::class, 'reschedule']);
    Route::patch('/tasks/{id}/reminder', [TaskController::class, 'scheduleReminder']);
    Route::post('/tasks/{id}/remind', [TaskController::class, 'remind']);
    Route::patch('/tasks/{id}/accept', [TaskController::class, 'acceptTask']);
    Route::patch('/tasks/{id}/reject', [TaskController::class, 'rejectTask']);
    
    Route::get('/tasks/{id}/activities', [TaskController::class, 'getActivities']);
    Route::post('/tasks/{id}/comments', [TaskController::class, 'addComment']);
    Route::post('/tasks/{id}/attachments', [TaskController::class, 'uploadAttachment']);
    Route::delete('/tasks/{taskId}/attachments/{attachmentId}', [TaskController::class, 'deleteAttachment']);

    // Partner/Employee assignment helpers
    Route::get('/partner/users', [TaskController::class, 'getPartnerEmployees']);

    // Task automation templates
    Route::get('/task-automation-templates', [TaskAutomationTemplateController::class, 'index']);
    Route::post('/task-automation-templates', [TaskAutomationTemplateController::class, 'store']);
    Route::get('/task-automation-templates/{id}', [TaskAutomationTemplateController::class, 'show']);
    Route::put('/task-automation-templates/{id}', [TaskAutomationTemplateController::class, 'update']);
    Route::delete('/task-automation-templates/{id}', [TaskAutomationTemplateController::class, 'destroy']);

    // Task automations (scheduled instances)
    Route::get('/task-automations', [TaskAutomationController::class, 'index']);
    Route::post('/task-automations', [TaskAutomationController::class, 'store']);
    Route::get('/task-automations/{id}', [TaskAutomationController::class, 'show']);
    Route::patch('/task-automations/{id}', [TaskAutomationController::class, 'update']);
    Route::delete('/task-automations/{id}', [TaskAutomationController::class, 'destroy']);

    // ... other routes
});
// Appointments
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::get('/appointments/my', [BookingController::class, 'myAppointments']);
    Route::post('/appointments/admin', [BookingController::class, 'adminStoreAppointment']);
    Route::get('/appointments/admin', [BookingController::class, 'adminAppointments']);
    Route::get('/appointments/boat/{id}', [BookingController::class, 'boatAppointments']);
});
// Find this line at the bottom of routes/api.php and change it to:
Route::post('/verify-password', [ProfileController::class, 'verifyPassword'])->middleware(['auth:sanctum', 'onboarding.active']);
Route::post('/notifications', [NotificationController::class, 'store'])->middleware(['auth:sanctum', 'onboarding.active']);

// Deal + contract orchestration
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::get('/deals/{dealId}/signhost/url', [DealController::class, 'getSignhostUrl']);
    Route::get('/deals/{dealId}/status', [DealController::class, 'status']);
    Route::get('/deals/{dealId}/payments/{type}/checkout-url', [DealController::class, 'getCheckoutUrl']);
});

Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::post('/deals/{dealId}/contract/generate', [DealController::class, 'generateContract'])
        ->middleware('action:deal.contract.generate');
    Route::post('/deals/{dealId}/signhost/create', [DealController::class, 'createSignhost'])
        ->middleware('action:deal.signhost.create');
    Route::post('/deals/{dealId}/payments/deposit/create', [DealController::class, 'createDepositPayment'])
        ->middleware('action:deal.payments.deposit.create');
    Route::post('/deals/{dealId}/payments/platform-fee/create', [DealController::class, 'createPlatformFeePayment'])
        ->middleware('action:deal.payments.platform_fee.create');
    Route::post('/wallet/topup', [WalletController::class, 'createTopup'])
        ->middleware('action:wallet.topup.create');
});

// Invoice documents (immutable storage + audit)
Route::middleware(['auth:sanctum', 'onboarding.active'])->prefix('invoices')->group(function () {
    Route::get('/documents', [InvoiceDocumentController::class, 'index']);
    Route::post('/documents', [InvoiceDocumentController::class, 'store']);
    Route::get('/documents/{id}', [InvoiceDocumentController::class, 'show']);
    Route::get('/documents/{id}/download', [InvoiceDocumentController::class, 'download']);
    Route::get('/documents/{id}/history', [InvoiceDocumentController::class, 'history']);
    Route::patch('/documents/{id}/status', [InvoiceDocumentController::class, 'updateStatus']);
    Route::patch('/documents/{id}/extraction', [InvoiceDocumentController::class, 'updateExtraction']);
});

Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::get('/wallet/ledger', [WalletController::class, 'ledger']);
    Route::get('/wallet/balances', [WalletController::class, 'balances']);
    Route::get('/wallets/{user}/ledger', [WalletController::class, 'ledgerForUser']);

    Route::get('/harbors/forecast', [HarborForecastController::class, 'myForecast']);
    Route::get('/harbors/{harbor}/forecast', [HarborForecastController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::post('/copilot/resolve', [CopilotController::class, 'resolve']);
    Route::post('/copilot/track', [CopilotController::class, 'track']);
    Route::get('/copilot/audit', [CopilotAuditController::class, 'index']);
    Route::get('/copilot/voice-settings', [CopilotVoiceSettingsController::class, 'show']);
    Route::put('/copilot/voice-settings', [CopilotVoiceSettingsController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'onboarding.active'])->prefix('admin/copilot')->group(function () {
    Route::get('/action-catalog', [CopilotActionCatalogController::class, 'index']);
    Route::post('/draft', [CopilotActionWorkflowController::class, 'draft']);
    Route::post('/validate', [CopilotActionWorkflowController::class, 'validateAction']);
    Route::post('/execute', [CopilotActionWorkflowController::class, 'execute']);

    Route::get('/actions', [CopilotActionController::class, 'index']);
    Route::post('/actions', [CopilotActionController::class, 'store']);
    Route::get('/actions/{action}', [CopilotActionController::class, 'show']);
    Route::put('/actions/{action}', [CopilotActionController::class, 'update']);
    Route::delete('/actions/{action}', [CopilotActionController::class, 'destroy']);

    Route::get('/phrases', [CopilotActionPhraseController::class, 'index']);
    Route::post('/phrases', [CopilotActionPhraseController::class, 'store']);
    Route::put('/phrases/{phrase}', [CopilotActionPhraseController::class, 'update']);
    Route::delete('/phrases/{phrase}', [CopilotActionPhraseController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'onboarding.active', 'admin.errors'])->prefix('admin/interaction')->group(function () {
    Route::get('/timeline', [InteractionHubController::class, 'timeline']);
    Route::get('/summary', [InteractionHubController::class, 'summary']);

    Route::get('/event-categories', [InteractionEventCategoryController::class, 'index']);
    Route::post('/event-categories', [InteractionEventCategoryController::class, 'store']);
    Route::put('/event-categories/{category}', [InteractionEventCategoryController::class, 'update']);
    Route::delete('/event-categories/{category}', [InteractionEventCategoryController::class, 'destroy']);

    Route::get('/event-types', [InteractionEventTypeController::class, 'index']);
    Route::post('/event-types', [InteractionEventTypeController::class, 'store']);
    Route::put('/event-types/{eventType}', [InteractionEventTypeController::class, 'update']);
    Route::delete('/event-types/{eventType}', [InteractionEventTypeController::class, 'destroy']);

    Route::get('/templates', [InteractionTemplateController::class, 'index']);
    Route::post('/templates', [InteractionTemplateController::class, 'store']);
    Route::put('/templates/{template}', [InteractionTemplateController::class, 'update']);
    Route::delete('/templates/{template}', [InteractionTemplateController::class, 'destroy']);
});

// Webhooks (no auth)
Route::post('/webhooks/mollie', [WebhookController::class, 'mollie']);
Route::post('/webhooks/signhost', [WebhookController::class, 'signhost']);
Route::post('/webhooks/whatsapp/360dialog', [WhatsApp360DialogWebhookController::class, 'handle']);
Route::post('/webhooks/telnyx/voice', [TelnyxVoiceWebhookController::class, 'handle']);
Route::post('/webhooks/francis/sync', [\App\Http\Controllers\Api\BoatVideoController::class, 'handleWebhook']);

// Internal voice gateway callbacks
Route::post('/internal/voice/transcript', [VoiceTranscriptController::class, 'store'])
    ->middleware('internal.secret');

// Harbor widget events (public)
Route::post('/harbor/widget/events', [HarborWidgetEventController::class, 'store']);
// Harbor funnel events (public)
Route::post('/harbor/funnel/events', [HarborFunnelEventController::class, 'store']);

// Public user-friendly error messages by reference
Route::get('/error-message/{reference}', [UserErrorMessageController::class, 'show']);
Route::post('/report-issue', [\App\Http\Controllers\ReportIssueController::class, 'store']);

// Sentry webhook (no auth, signature optional via SENTRY_WEBHOOK_SECRET)
Route::post('/sentry/webhook', [SentryWebhookController::class, 'handle']);

// Admin Errors Control Center
Route::middleware(['auth:sanctum', 'onboarding.active', 'admin.errors'])->prefix('admin/errors')->group(function () {
    Route::get('/', [PlatformErrorController::class, 'index']);
    Route::get('/stats', [PlatformErrorController::class, 'stats']);
    Route::get('/{error}', [PlatformErrorController::class, 'show']);
    Route::post('/{error}/resolve', [PlatformErrorController::class, 'resolve']);
    Route::post('/{error}/ignore', [PlatformErrorController::class, 'ignore']);
    Route::post('/{error}/note', [PlatformErrorController::class, 'note']);
    Route::post('/{error}/assign', [PlatformErrorController::class, 'assign']);
});

// Harbor widget monitoring (admin/staff)
Route::middleware(['auth:sanctum', 'onboarding.active', 'admin.errors'])->group(function () {
    Route::get('/admin/harbor-widget/overview', [HarborWidgetAdminController::class, 'overview']);
    Route::get('/admin/harbors/{harbor}/widget/weekly', [HarborWidgetAdminController::class, 'weekly']);
    Route::get('/admin/harbors/{harbor}/widget/snapshots', [HarborWidgetAdminController::class, 'snapshots']);
    Route::get('/admin/harbors/{harbor}/widget/settings', [HarborWidgetAdminController::class, 'settings']);
    Route::post('/admin/harbors/{harbor}/widget/settings', [HarborWidgetAdminController::class, 'upsertSettings']);
    Route::put('/admin/harbors/{harbor}/widget/settings', [HarborWidgetAdminController::class, 'upsertSettings']);
    Route::get('/admin/harbors/{harbor}/booking-settings', [\App\Http\Controllers\HarborBookingSettingController::class, 'show']);
    Route::post('/admin/harbors/{harbor}/booking-settings', [\App\Http\Controllers\HarborBookingSettingController::class, 'upsert']);
    Route::put('/admin/harbors/{harbor}/booking-settings', [\App\Http\Controllers\HarborBookingSettingController::class, 'upsert']);
    Route::get('/admin/harbors/performance', [HarborPerformanceController::class, 'index']);
    Route::get('/admin/harbors/{harbor}/performance', [HarborPerformanceController::class, 'show']);
});

// Harbor partner performance (partner-only)
Route::middleware(['auth:sanctum', 'onboarding.active'])->group(function () {
    Route::get('/harbors/performance', [HarborPerformanceController::class, 'myPerformance']);
});
// ================== YACHTSHIFT FEED ==================

Route::get('/yachtshift/feed', [YachtShiftFeedController::class, 'fetch']);
Route::post('/yachtshift/feed/refresh', [YachtShiftFeedController::class, 'refresh']);
Route::get('/yachtshift/boats', [YachtShiftFeedController::class, 'listFromDatabase']);

// ================== HARBORS ==================

// Public harbor routes
Route::get('/harbors', [HarborController::class, 'index']);
Route::get('/harbors/slug/{slug}', [HarborController::class, 'showBySlug']);
Route::get('/harbors/{id}', [HarborController::class, 'show']);
Route::get('/harbor-pages/{harborId}', [HarborPageController::class, 'show']);
Route::get('/harbor-pages/{harborId}/{locale}', [HarborPageController::class, 'showByLocale']);

// Admin harbor routes
Route::middleware(['auth:sanctum', 'onboarding.active'])->prefix('admin/harbors')->group(function () {
    Route::get('/', [HarborController::class, 'adminIndex']);
    Route::get('/stats', [HarborController::class, 'stats']);
    Route::get('/needs-review', [HarborController::class, 'needsReview']);
    Route::get('/missing-contacts', [HarborController::class, 'adminIndex']);
    Route::post('/', [HarborController::class, 'store']);
    Route::put('/{id}', [HarborController::class, 'update']);
    Route::delete('/{id}', [HarborController::class, 'destroy']);
    Route::post('/{id}/enrich', [HarborController::class, 'enrich']);
    Route::post('/{id}/geocode', [HarborController::class, 'geocode']);
    Route::post('/{id}/place-details', [HarborController::class, 'placeDetails']);
    Route::post('/{id}/third-party-enrich', [HarborController::class, 'thirdPartyEnrich']);
    Route::post('/{id}/generate-page', [HarborController::class, 'generatePage']);
    Route::post('/{id}/toggle-publish', [HarborController::class, 'togglePublish']);
    Route::get('/export-magazine', [HarborController::class, 'exportMagazine']);
});
