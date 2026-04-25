<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Models\YachtImage;
use App\Models\YachtAiExtraction;
use App\Models\User;
use App\Services\AiCorrectionLoggingService;
use App\Services\BoatTaskAutomationService;
use App\Services\KnowledgeGraphService;
use App\Services\BoatTemplateService;
use App\Services\Ga4MeasurementService;
use App\Services\LocationAccessService;
use App\Services\StickerService;
use App\Services\SyncYachtTasksService;
use App\Services\VideoAutomationService;
use App\Support\YachtImageLimits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class YachtController extends Controller
{
    private const ALLOWED_CHANGED_BY_TYPES = ['ai', 'user', 'admin', 'import', 'scraper'];
    private const ALLOWED_SOURCE_TYPES = ['manual', 'image', 'text', 'api', 'inferred', 'import', 'scraper', 'system'];
    private const ALLOWED_CORRECTION_LABELS = [
        'wrong_image_detection',
        'wrong_text_interpretation',
        'guessed_too_much',
        'duplicate_data_issue',
        'import_mismatch',
        'other',
    ];

    public function __construct(
        private readonly LocationAccessService $locationAccess,
        private readonly AiCorrectionLoggingService $correctionLogging,
        private readonly VideoAutomationService $videoAutomation,
        private readonly KnowledgeGraphService $knowledgeGraph,
        private readonly StickerService $stickerService
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $hasPaginationRequest = $request->hasAny([
            'per_page',
            'page',
            'search',
            'status',
            'sort_by',
            'sort_dir',
        ]);

        if (! $hasPaginationRequest) {
            return response()->json(
                $this->visibleYachtsQuery($request->user())
                    ->orderBy('boat_name', 'asc')
                    ->get()
            );
        }

        $perPage = (int) $request->integer('per_page', 25);
        if (! in_array($perPage, [25, 50], true)) {
            $perPage = 25;
        }

        $sortBy = (string) $request->input('sort_by', 'boat_name');
        $allowedSorts = ['boat_name', 'price', 'year', 'created_at', 'updated_at', 'vessel_id', 'manufacturer', 'model', 'status', 'location_city'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'boat_name';
        }

        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));

        $query = $this->visibleYachtsQuery($request->user());
        $stats = $this->buildFleetStats(clone $query);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('boat_name', 'like', "%{$search}%")
                    ->orWhere('vessel_id', 'like', "%{$search}%")
                    ->orWhere('manufacturer', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('location_city', 'like', "%{$search}%");
            });
        }

        if ($status !== '' && strtolower($status) !== 'all') {
            $query->whereRaw("LOWER(COALESCE(status, 'draft')) = ?", [strtolower($status)]);
        }

        if ($sortBy === 'price') {
            $query->orderByRaw("COALESCE(price, min_bid_amount, 0) {$sortDir}");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
            'stats' => $stats,
        ]);
    }

    public function partnerIndex(): JsonResponse
    {
        $user = Auth::user();

        return response()->json(
            $this->visibleYachtsQuery($user)
                ->where('user_id', $user->id)
                ->orderBy('boat_name', 'asc')
                ->get()
        );
    }


    public function store(Request $request): JsonResponse
    {
        return $this->saveYacht($request);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return $this->saveYacht($request, $id);
    }

    protected function saveYacht(Request $request, $id = null): JsonResponse
    {
        $offlineUuid = $request->header('X-Offline-ID');
        if (! $id && $offlineUuid) {
            $existing = Yacht::where('offline_uuid', $offlineUuid)->first();
            if ($existing) {
                $this->authorizeYachtAccess($request->user(), $existing);
                $existing->load(['images', 'availabilityRules']);

                return response()->json($existing, 200);
            }
        }

        $actor = $request->user();
        if ($id === null && $actor?->isClient()) {
            $eligibilityError = $this->clientBoatCreationEligibilityError($actor);
            if ($eligibilityError !== null) {
                return response()->json($eligibilityError, 422);
            }
        }

        try {
            DB::beginTransaction();

            $isUpdate = $id !== null;
            if ($this->shouldBootstrapDraft($request, $isUpdate)) {
                $draft = $this->createBootstrapDraft($request, $actor, $offlineUuid);
                DB::commit();
                $this->syncYachtKnowledgeSafely($draft);

                return response()->json($draft, 201);
            }

            $yacht = $isUpdate ? Yacht::findOrFail($id) : new Yacht();
            if ($isUpdate) {
                $this->authorizeYachtAccess($actor, $yacht);
            }
            // Auto-generate boat name if not provided (for both new and update)
            if (! $request->has('boat_name') || empty($request->input('boat_name'))) {
                $manufacturer = $request->input('manufacturer', '');
                $model = $request->input('model', '');
                $autoName = trim("$manufacturer $model");

                if (empty($autoName)) {
                    $autoName = 'Yacht '.date('Y-m-d H:i');
                }

                $request->merge(['boat_name' => $autoName]);
            }

            $coreFields = [
                'boat_name', 'price', 'status', 'year', 'main_image', 'min_bid_amount',
                'auction_mode', 'auction_start', 'auction_end', 'auction_duration_minutes', 'auction_extension_seconds',
                'external_url', 'print_url', 'owners_comment', 'reg_details',
                'known_defects', 'last_serviced',
                'boat_type', 'boat_category', 'new_or_used', 'manufacturer', 'model',
                'vessel_lying', 'location_city', 'location_lat', 'location_lng',
                'short_description_nl', 'short_description_en', 'short_description_de', 'short_description_fr', 'advertise_as',
                'ce_category', 'ce_max_weight', 'ce_max_motor', 'cvo', 'cbb',
                'open_cockpit', 'aft_cockpit', 'ballast_tank',
                'steering_system', 'steering_system_location',
                'remote_control', 'rudder', 'drift_restriction',
                'drift_restriction_controls', 'trimflaps', 'stabilizer',
            ];
            $booleanFields = ['allow_bidding', 'auction_enabled'];
            $trackableFields = $this->buildTrackableFields($coreFields, $booleanFields);
            $submittedFields = $this->extractSubmittedTrackableFields($request, $trackableFields);
            $beforeSnapshot = $this->captureBeforeSnapshot($yacht, $submittedFields, $isUpdate);

            foreach ($coreFields as $field) {
                if (! $request->has($field)) {
                    continue;
                }

                $value = $request->input($field);
                $yacht->{$field} = ($value === '' || $value === 'undefined' || $value === null)
                    ? null
                    : $value;
            }

            foreach ($booleanFields as $field) {
                if ($request->has($field)) {
                    $yacht->{$field} = filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN);
                } elseif (! $isUpdate) {
                    $yacht->{$field} = false;
                }
            }

            if ($request->has('auction_mode')) {
                $auctionMode = strtolower(trim((string) $request->input('auction_mode')));
                $yacht->auction_mode = in_array($auctionMode, ['bids', 'live'], true) ? $auctionMode : null;
            }

            if ($request->has('auction_enabled') && $yacht->auction_enabled && in_array($yacht->auction_mode, ['bids', 'live'], true)) {
                $yacht->allow_bidding = true;
            }

            if ($request->hasFile('main_image')) {
                if ($isUpdate && $yacht->main_image) {
                    Storage::disk('public')->delete($yacht->main_image);
                }

                $yacht->main_image = $request->file('main_image')->store('yachts/main', 'public');
            } elseif ($request->has('main_image') && is_string($request->input('main_image'))) {
                // Only accept a text main_image value if it belongs to this yacht's own images.
                // This prevents cross-yacht image contamination from AI extraction or copy-paste.
                $candidatePath = $request->input('main_image');
                if ($candidatePath && $isUpdate) {
                    $ownedByThisYacht = $yacht->images()
                        ->where(function ($q) use ($candidatePath) {
                            $q->where('url', $candidatePath)
                              ->orWhere('optimized_master_url', $candidatePath)
                              ->orWhere('full_url', $candidatePath);
                        })->exists();
                    // Also allow paths that contain the yacht's own folder name
                    $inYachtFolder = str_contains($candidatePath, "/{$yacht->id}/")
                        || str_contains($candidatePath, 'yachts/main/');
                    if ($ownedByThisYacht || $inYachtFolder) {
                        $yacht->main_image = $candidatePath;
                    }
                    // If not owned by this yacht, silently ignore — keep existing main_image
                } elseif ($candidatePath && ! $isUpdate) {
                    $yacht->main_image = $candidatePath;
                }
            }

            $this->applyRequestedLocation($request, $yacht, $actor, $isUpdate);
            $this->applyRequestedOwner($request, $yacht, $actor, $isUpdate);

            // Hydrate coordinates from Location
            if (empty($yacht->location_lat) && !empty($yacht->location_id)) {
                $location = \App\Models\Location::find($yacht->location_id);
                if ($location && !empty($location->lat)) {
                    $yacht->location_lat = $location->lat;
                    $yacht->location_lng = $location->lng;
                }
            }

            if (! $isUpdate) {
                if (! $yacht->vessel_id) {
                    $yacht->vessel_id = 'SK-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3)));
                }

                if ($offlineUuid) {
                    $yacht->offline_uuid = $offlineUuid;
                }
            }

            if (empty($yacht->min_bid_amount) && ! empty($yacht->price)) {
                $yacht->min_bid_amount = $yacht->price * 0.9;
            }

            $yacht->save();

            // Sync vessel_lying → construction.where to keep location data consistent
            $subTableData = $request->all();
            if (
                (! array_key_exists('where', $subTableData) || $subTableData['where'] === '' || $subTableData['where'] === 'undefined' || $subTableData['where'] === null)
                && ! empty($yacht->vessel_lying)
            ) {
                $subTableData['where'] = $yacht->vessel_lying;
            }
            $yacht->saveSubTables($subTableData);

            if ($request->filled('availability_rules')) {
                try {
                    $rules = json_decode($request->input('availability_rules'), true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($rules)) {
                        $yacht->availabilityRules()->delete();

                        foreach ($rules as $rule) {
                            if (! empty($rule['day_of_week']) && ! empty($rule['start_time']) && ! empty($rule['end_time'])) {
                                $yacht->availabilityRules()->create([
                                    'day_of_week' => (int) $rule['day_of_week'],
                                    'start_time' => $rule['start_time'],
                                    'end_time' => $rule['end_time'],
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to save availability rules: '.$e->getMessage());
                }
            }

            $savedYacht = Yacht::query()->findOrFail($yacht->id);
            $afterSnapshot = $this->buildSnapshotForFields($savedYacht->toArray(), $submittedFields);
            $aiExtraction = $this->findAiExtraction($request);
            $loggingContext = $this->buildFieldLoggingContext($request, $actor, $aiExtraction, $isUpdate);

            if ($submittedFields !== []) {
                $this->correctionLogging->logFieldDiffs(
                    $savedYacht->id,
                    $beforeSnapshot,
                    $afterSnapshot,
                    $loggingContext
                );
            }

            app(SyncYachtTasksService::class)->syncForYacht($savedYacht, $actor);

            // --- Template auto-update ---
            try {
                $brand = trim((string) $savedYacht->manufacturer);
                $model = trim((string) $savedYacht->model);
                if (! empty($brand) && ! empty($model)) {
                    app(BoatTemplateService::class)->updateTemplateFromBoatSave(
                        $savedYacht->fresh(['dimensions', 'construction', 'engine', 'accommodation',
                            'electrical', 'navigation', 'safety', 'comfort',
                            'deckEquipment', 'rigging'])
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[YachtController] Template update after save failed: '.$e->getMessage());
            }

            DB::commit();

            $yacht = $savedYacht->load([
                'images',
                'availabilityRules',
                'latestSignRequest',
                'location:id,name,code,status,chat_widget_enabled,chat_widget_welcome_text,chat_widget_theme',
                'owner:id,name,first_name,last_name,email,phone,address_line1,address_line2,postal_code,city,country,client_location_id',
            ]);

            $this->syncYachtKnowledgeSafely($yacht);

            // Fire task automation for newly created yachts
            if (! $isUpdate && $yacht->boat_type) {
                try {
                    app(BoatTaskAutomationService::class)->fireForYacht($yacht, $actor);
                } catch (\Throwable $e) {
                    Log::warning('[BoatTaskAutomation] Non-critical failure: ' . $e->getMessage());
                }
            }

            $this->triggerAutomaticVideoFlows($yacht, ! $isUpdate);

            $oldStatusStr = strtolower((string) ($beforeSnapshot['status'] ?? 'draft'));
            $newStatusStr = strtolower((string) ($yacht->status ?? ''));
            if ($oldStatusStr !== $newStatusStr && in_array($newStatusStr, ['active', 'for sale'], true)) {
                $this->triggerSignhostContract($yacht, $actor);
            }

            // Sync/Generate QR Sticker
            try {
                $this->stickerService->syncForYacht($yacht);
            } catch (\Throwable $e) {
                Log::warning('[StickerService] Auto-sync failed: ' . $e->getMessage());
            }

            return response()->json($yacht, $isUpdate ? 200 : 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Yacht Save Error: '.$e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'yacht_id' => $id ?? 'new',
            ]);

            return response()->json([
                'message' => 'Failed to save yacht',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function shouldBootstrapDraft(Request $request, bool $isUpdate): bool
    {
        if ($isUpdate) {
            return false;
        }

        if (strtolower((string) $request->input('status')) !== 'draft') {
            return false;
        }

        if ($request->hasFile('main_image')) {
            return false;
        }

        $allowedKeys = ['status', 'location_id'];
        $presentKeys = array_keys($request->except(['_method']));

        foreach ($presentKeys as $key) {
            if (! in_array($key, $allowedKeys, true)) {
                return false;
            }
        }

        return true;
    }

    private function createBootstrapDraft(Request $request, ?User $actor, ?string $offlineUuid): Yacht
    {
        $yacht = new Yacht();
        $yacht->status = 'draft';
        $yacht->boat_name = 'Yacht '.date('Y-m-d H:i');
        $yacht->allow_bidding = false;

        $this->applyRequestedLocation($request, $yacht, $actor, false);
        $this->applyRequestedOwner($request, $yacht, $actor, false);

        if (! $yacht->vessel_id) {
            $yacht->vessel_id = 'SK-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3)));
        }

        if ($offlineUuid) {
            $yacht->offline_uuid = $offlineUuid;
        }

        $yacht->save();

        return $yacht;
    }

    private function applyRequestedLocation(Request $request, Yacht $yacht, ?User $actor, bool $isUpdate): void
    {
        if ($request->has('location_id')) {
            $requestedLocationId = $request->integer('location_id') ?: null;

            if ($actor?->isClient()) {
                $yacht->location_id = $actor->client_location_id;
            } elseif ($actor?->isEmployee()) {
                if ($requestedLocationId !== null && ! $this->locationAccess->sharesLocation($actor, $requestedLocationId)) {
                    abort(403, 'Forbidden');
                }

                $yacht->location_id = $requestedLocationId;
            } else {
                $yacht->location_id = $requestedLocationId;
            }
        } elseif (! $isUpdate && $actor?->client_location_id) {
            $yacht->location_id = $actor->client_location_id;
        }
    }

    private function applyRequestedOwner(Request $request, Yacht $yacht, ?User $actor, bool $isUpdate): void
    {
        if ($request->has('user_id')) {
            $requestedUserId = $request->input('user_id');

            if ($requestedUserId === null || $requestedUserId === '') {
                if (! $actor?->isClient()) {
                    $yacht->user_id = null;
                }

                return;
            }

            $owner = User::query()->findOrFail((int) $requestedUserId);

            if (! $owner->isClient()) {
                abort(422, 'The linked yacht owner must be a client user.');
            }

            if ($actor?->isEmployee() && ! $this->locationAccess->sharesLocation($actor, $owner->client_location_id)) {
                abort(403, 'Forbidden');
            }

            $yacht->user_id = $owner->id;

            if (! $yacht->location_id && $owner->client_location_id) {
                $yacht->location_id = (int) $owner->client_location_id;
            }

            return;
        }

        if (! $isUpdate && $actor?->isClient()) {
            $yacht->user_id = $actor->id;
        }
    }

    private function clientBoatCreationEligibilityError(User $actor): ?array
    {
        $requiredProfileFields = [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'phone' => 'phone',
            'date_of_birth' => 'date of birth',
            'address_line1' => 'address line 1',
            'city' => 'city',
            'postal_code' => 'postal code',
            'country' => 'country',
        ];

        $missingFieldKeys = [];
        $missingFieldLabels = [];

        foreach ($requiredProfileFields as $field => $label) {
            $value = $actor->{$field};
            if ($value === null || trim((string) $value) === '') {
                $missingFieldKeys[] = $field;
                $missingFieldLabels[] = $label;
            }
        }

        $identityVerified = method_exists($actor, 'hasVerifiedEmail')
            ? $actor->hasVerifiedEmail()
            : ! empty($actor->email_verified_at);

        if ($missingFieldKeys === [] && $identityVerified) {
            return null;
        }

        $messageParts = [];
        if ($missingFieldLabels !== []) {
            $messageParts[] = 'Complete all customer details before creating a vessel. Missing: ' . implode(', ', $missingFieldLabels) . '.';
        }
        if (! $identityVerified) {
            $messageParts[] = 'Verify your account identity before creating a vessel.';
        }

        return [
            'message' => implode(' ', $messageParts),
            'error_code' => 'client_profile_incomplete_for_boat_creation',
            'requirements' => [
                'missing_profile_fields' => $missingFieldKeys,
                'identity_verification_required' => ! $identityVerified,
            ],
            'errors' => array_filter([
                'profile' => $missingFieldLabels !== []
                    ? ['Missing required customer details: ' . implode(', ', $missingFieldLabels) . '.']
                    : null,
                'identity_verification' => ! $identityVerified
                    ? ['Account identity verification is required before creating a vessel.']
                    : null,
            ]),
        ];
    }


    public function uploadGallery(Request $request, $id): JsonResponse {
        $request->validate([
            'category' => 'nullable|string|in:Exterior,Interior,Engine Room,Bridge,General',
        ]);

        $yacht = Yacht::findOrFail($id);
        if ($request->user()) {
            $this->authorizeYachtAccess($request->user(), $yacht);
        }
        
        $files = $request->file('images') 
            ?? $request->file('images[]') 
            ?? $request->file('file')
            ?? $request->file('files');

        // Fallback: grab any uploaded files regardless of field name
        if (empty($files)) {
            $allFiles = $request->allFiles();
            if (!empty($allFiles)) {
                $files = [];
                foreach ($allFiles as $key => $value) {
                    if (is_array($value)) {
                        $files = array_merge($files, $value);
                    } else {
                        $files[] = $value;
                    }
                }
            }
        }

        if (empty($files)) {
            Log::warning('Upload debug: no files found', [
                'content_type' => $request->header('Content-Type'),
                'all_keys' => array_keys($request->all()),
                'has_files' => $request->allFiles() ? 'yes' : 'no',
            ]);
            return response()->json(['message' => 'No images detected'], 422);
        }

        $files = is_array($files) ? $files : [$files];
        $uploaded = [];
        $currentCount = $yacht->images()->whereNotIn('status', ['deleted'])->count();

        foreach ($files as $index => $image) {
            if ($image instanceof \Illuminate\Http\UploadedFile) {
                $folderName = $yacht->vessel_id ?? $yacht->id;
                
                // Store in original_temp for pipeline processing
                $path = $image->store("original_temp/{$folderName}", 'public');
                
                $record = $yacht->images()->create([
                    'url'               => $path,
                    'original_temp_url' => $path,
                    'original_name'     => $image->getClientOriginalName(),
                    'category'          => $request->input('category', 'General'),
                    'part_name'         => $request->input('category', 'General'),
                    'status'            => 'ready_for_review',
                    'keep_original'     => false,
                    'sort_order'        => $currentCount + $index,
                ]);

                // Queue the optimizer after the response so the card is visible immediately.
                \App\Jobs\ProcessYachtImageJob::dispatchAfterResponse($record->id);
                \App\Jobs\EnhanceYachtImageJob::dispatch($record->id)->delay(now()->addSeconds(2));

                $uploaded[] = $record->fresh();
            }
        }

        if ($uploaded !== []) {
            $this->triggerAutomaticVideoFlows($yacht->fresh(['images', 'owner']), false);
        }

        return response()->json(['status' => 'success', 'data' => $uploaded], 200);
    }

    /**
     * Get all images for a yacht with stats.
     */
    public function getImages(int $id): JsonResponse
    {
        $yacht = Yacht::findOrFail($id);
        $images = $yacht->images()->orderBy('sort_order')->get();

        $stats = [
            'total'        => $images->count(),
            'approved'     => $images->where('status', 'approved')->count(),
            'processing'   => $images->where('status', 'processing')->count(),
            'ready'        => $images->where('status', 'ready_for_review')->count(),
            'min_required' => 1,
        ];

        return response()->json([
            'images'          => $images,
            'stats'           => $stats,
            'step2_unlocked'  => $stats['approved'] >= $stats['min_required'],
        ]);
    }

    /**
     * Approve an image (makes it available for exports/social/video).
     */
    public function approveImage(Request $request, int $id): JsonResponse
    {
        $image = YachtImage::findOrFail($id);
        $yacht = Yacht::findOrFail($image->yacht_id);
        $this->authorizeYachtAccess($request->user(), $yacht);

        if (!in_array($image->status, ['ready_for_review', 'rejected'])) {
            return response()->json([
                'message' => "Cannot approve image with status '{$image->status}'",
            ], 422);
        }

        $image->update(['status' => 'approved']);
        $this->triggerAutomaticVideoFlows($yacht->fresh(['images', 'owner']), false);

        return response()->json(['message' => 'Image approved', 'image' => $image]);
    }

    /**
     * Reject an image (excluded from exports/social/video).
     */
    public function rejectImage(Request $request, int $id): JsonResponse
    {
        $image = YachtImage::findOrFail($id);
        $yacht = Yacht::findOrFail($image->yacht_id);
        $this->authorizeYachtAccess($request->user(), $yacht);

        if (!in_array($image->status, ['ready_for_review', 'approved'])) {
            return response()->json([
                'message' => "Cannot reject image with status '{$image->status}'",
            ], 422);
        }

        $image->update(['status' => 'rejected']);

        return response()->json(['message' => 'Image rejected', 'image' => $image]);
    }

    /**
     * Bulk approve/reject images.
     */
    public function bulkImageAction(Request $request): JsonResponse
    {
        $request->validate([
            'image_ids' => 'required|array|min:1',
            'image_ids.*' => 'integer|exists:yacht_images,id',
            'action' => 'required|in:approve,reject',
        ]);

        $newStatus = $request->action === 'approve' ? 'approved' : 'rejected';
        $validStatuses = ['ready_for_review', $request->action === 'approve' ? 'rejected' : 'approved'];

        $updated = YachtImage::whereIn('id', $request->image_ids)
            ->whereIn('status', $validStatuses)
            ->update(['status' => $newStatus]);

        if ($newStatus === 'approved' && $updated > 0) {
            $yachtIds = YachtImage::query()
                ->whereIn('id', $request->image_ids)
                ->pluck('yacht_id')
                ->unique()
                ->filter()
                ->values();

            foreach ($yachtIds as $yachtId) {
                $yacht = Yacht::query()->with(['images', 'owner'])->find($yachtId);
                if ($yacht) {
                    $this->triggerAutomaticVideoFlows($yacht, false);
                }
            }
        }

        return response()->json([
            'message' => "{$updated} images {$request->action}d",
            'count' => $updated,
        ]);
    }

    public function deleteGalleryImage($id): JsonResponse {
        $image = YachtImage::findOrFail($id);
        $yacht = Yacht::findOrFail($image->yacht_id);
        $this->authorizeYachtAccess(Auth::user(), $yacht);

        // Delete all stored variants from disk (not just the original url)
        $pathsToDelete = array_filter(array_unique([
            $image->url,
            $image->full_url,
            $image->optimized_url,
            $image->optimized_master_url,
            $image->thumb_url,
            $image->thumb_full_url,
            $image->original_temp_url,
        ]), fn($p) => !empty($p) && !str_starts_with($p, 'http'));

        foreach ($pathsToDelete as $path) {
            Storage::disk('public')->delete($path);
        }

        // If this image was set as the yacht's main_image, clear it and
        // replace with the next available gallery image
        $deletedPaths = array_filter([
            $image->url,
            $image->optimized_master_url,
            $image->optimized_url,
            $image->full_url,
        ]);
        if (in_array($yacht->main_image, $deletedPaths, true)) {
            $next = YachtImage::where('yacht_id', $yacht->id)
                ->where('id', '!=', $image->id)
                ->whereIn('status', ['approved', 'ready_for_review'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
            $yacht->main_image = $next
                ? ($next->optimized_master_url ?? $next->optimized_url ?? $next->url)
                : null;
            $yacht->save();
        }

        $image->delete();

        return response()->json([
            'message' => 'Image removed',
            'new_main_image' => $yacht->main_image,
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $yacht = $this->visibleYachtsQuery($request->user())->find($id);
        if (! $yacht) {
            return response()->json(['message' => 'Vessel not found'], 404);
        }

        return response()->json($yacht);
    }

    public function destroy($id): JsonResponse
    {
        $yacht = Yacht::findOrFail($id);
        $this->authorizeYachtAccess(Auth::user(), $yacht);

        if ($yacht->main_image) {
            Storage::disk('public')->delete($yacht->main_image);
        }

        // Delete all stored variants of each gallery image, not just the primary url
        foreach ($yacht->images as $img) {
            $pathsToDelete = array_filter(array_unique([
                $img->url,
                $img->full_url,
                $img->optimized_url,
                $img->optimized_master_url,
                $img->thumb_url,
                $img->thumb_full_url,
                $img->original_temp_url,
            ]), fn($p) => !empty($p) && !str_starts_with($p, 'http'));

            foreach ($pathsToDelete as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        $yacht->delete();
        $this->removeYachtKnowledgeSafely($yacht);

        return response()->json(['message' => 'Vessel removed from fleet.']);
    }

    private function authorizeYachtAccess(?User $user, Yacht $yacht): void
    {
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isClient() && (int) $yacht->user_id === (int) $user->id) {
            return;
        }

        if ($user->isEmployee()) {
            $locationId = $this->resolveYachtLocationId($yacht);
            if ($locationId !== null && $this->locationAccess->sharesLocation($user, $locationId)) {
                return;
            }
        }

        abort(403, 'Forbidden');
    }

    private function visibleYachtsQuery(?User $user): Builder
    {
        $query = Yacht::query()->with([
            'images',
            'availabilityRules',
            'latestSignRequest',
            'owner:id,name,first_name,last_name,email,phone,address_line1,address_line2,postal_code,city,country,client_location_id',
        ]);

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isClient()) {
            return $query->where('user_id', $user->id);
        }

        if ($user->isEmployee()) {
            $locationIds = $this->locationAccess->accessibleLocationIds($user);
            if ($locationIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $builder) use ($locationIds) {
                $builder->whereIn('location_id', $locationIds)
                    ->orWhereHas('owner', function (Builder $ownerQuery) use ($locationIds) {
                        $ownerQuery->whereIn('client_location_id', $locationIds);
                    });
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function buildFleetStats(Builder $query): array
    {
        $counts = $query
            ->selectRaw("LOWER(COALESCE(status, 'draft')) as normalized_status, COUNT(*) as aggregate")
            ->groupBy('normalized_status')
            ->pluck('aggregate', 'normalized_status');

        return [
            'total' => (int) $counts->sum(),
            'forSale' => (int) ($counts['for sale'] ?? 0),
            'forBid' => (int) ($counts['for bid'] ?? 0),
            'sold' => (int) ($counts['sold'] ?? 0),
            'draft' => (int) ($counts['draft'] ?? 0),
            'active' => (int) ($counts['active'] ?? 0),
            'inactive' => (int) ($counts['inactive'] ?? 0),
            'maintenance' => (int) ($counts['maintenance'] ?? 0),
        ];
    }

    private function resolveYachtLocationId(Yacht $yacht): ?int
    {
        if ($yacht->location_id) {
            return (int) $yacht->location_id;
        }

        if ($yacht->relationLoaded('owner') && $yacht->owner?->client_location_id) {
            return (int) $yacht->owner->client_location_id;
        }

        if (! $yacht->user_id) {
            return null;
        }

        return User::query()
            ->whereKey($yacht->user_id)
            ->value('client_location_id');
    }

    private function triggerAutomaticVideoFlows(Yacht $yacht, bool $isNew): void
    {
        try {
            if ($isNew) {
                $this->videoAutomation->handleYachtCreated($yacht);
            }

            $this->videoAutomation->handleYachtPublished($yacht);
        } catch (\Throwable $e) {
            Log::warning('[VideoAutomation] Non-critical failure: '.$e->getMessage(), [
                'yacht_id' => $yacht->id,
                'is_new' => $isNew,
            ]);
        }
    }

    private function triggerSignhostContract(Yacht $yacht, ?User $actor): void
    {
        try {
            $generateAction = app(\App\Actions\Signhost\GenerateContractAction::class);
            $createAction = app(\App\Actions\Signhost\CreateSignhostRequestAction::class);

            $payload = [
                'location_id' => $yacht->location_id ?? 1,
                'entity_type' => 'Yacht',
                'entity_id' => $yacht->id,
                'title' => 'Brokerage Agreement - ' . $yacht->boat_name,
            ];

            $actionActor = $actor && !$actor->isClient() ? $actor : User::byRole('admin')->first();
            if (!$actionActor) {
                return;
            }

            $signRequest = $generateAction->execute($actionActor, $payload);

            $owner = $yacht->user_id ? User::find($yacht->user_id) : null;
            if ($owner && $owner->email) {
                $ownerName = $owner->name ?? trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? '')) ?: 'Yacht Owner';

                $createAction->execute($actionActor, [
                    'sign_request_id' => $signRequest->id,
                    'recipients' => [
                        [
                            'email' => $owner->email,
                            'name' => $ownerName,
                            'role' => 'seller',
                        ],
                    ],
                ], 'yacht-auto-contract-' . $yacht->id . '-' . time());
            }
        } catch (\Throwable $e) {
            Log::warning('[SignhostAutoContract] Non-critical failure: '.$e->getMessage(), [
                'yacht_id' => $yacht->id,
            ]);
        }
    }

    private function syncYachtKnowledgeSafely(Yacht $yacht): void
    {
        try {
            $this->knowledgeGraph->syncYacht($yacht);
        } catch (\Throwable $e) {
            Log::warning('Yacht knowledge sync failed after yacht save', [
                'yacht_id' => $yacht->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removeYachtKnowledgeSafely(Yacht $yacht): void
    {
        try {
            $this->knowledgeGraph->removeYacht($yacht);
        } catch (\Throwable $e) {
            Log::warning('Yacht knowledge cleanup failed after yacht delete', [
                'yacht_id' => $yacht->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function classifyImages(Request $request): JsonResponse
    {
        $request->validate([
            'images.*' => 'required|image|max:5120',
        ]);

        $apiKey = env('GEMINI_API_KEY');
        $model = "gemini-2.5-flash"; 
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $results = [];

        foreach ($request->file('images') as $image) {
            try {
                $imageData = base64_encode(file_get_contents($image->getRealPath()));
                
                $response = Http::timeout(15)->post($endpoint, [
                    'contents' => [['parts' => [
                        ['text' => "Return only one word: Exterior, Interior, Engine Room, Bridge, or General."],
                        ['inline_data' => ['mime_type' => $image->getMimeType(), 'data' => $imageData]]
                    ]]]
                ]);

                if ($response->successful()) {
                    $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'General';
                    $category = trim(preg_replace('/[^A-Za-z\s]/', '', $text));
                    // Validate category
                    $validCategories = ['Exterior', 'Interior', 'Engine Room', 'Bridge', 'General'];
                    if (!in_array($category, $validCategories)) {
                        $category = 'General';
                    }
                } else {
                    $category = 'General';
                }

                $results[] = [
                    'category' => $category,
                    'preview' => 'data:' . $image->getMimeType() . ';base64,' . $imageData,
                    'originalName' => $image->getClientOriginalName()
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'category' => 'General',
                    'preview' => '', 
                    'originalName' => $image->getClientOriginalName(),
                    'error' => true
                ];
            }
        }

        return response()->json($results);
    }

    /**
     * Extract structured boat data from images + optional hint text using Gemini 2.5 Flash.
     * Sends ALL images in a single API call with a strict JSON output schema.
     */
    public function extractFromImages(Request $request): JsonResponse
    {
        $request->validate([
            'images'    => 'required|array|min:1|max:'.YachtImageLimits::MAX_IMAGES_PER_YACHT,
            'images.*'  => 'required|image|max:10240',
            'hint_text' => 'nullable|string|max:2000',
        ]);

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return response()->json(['error' => 'GEMINI_API_KEY not configured'], 500);
        }

        $model    = "gemini-2.5-flash";
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        // Build the parts array: system instruction + hint + all images
        $parts = [];

        // System instruction
        $schema = <<<'SCHEMA'
You are a professional boat document OCR and data extraction agent.

RULES:
- Extract ONLY what you can see in the images or read from the hint text.
- NEVER invent or guess values. If a field is not visible → set it to null.
- If data conflicts (e.g. hint says 2016, document shows 2014) → set field to null and add a warning.
- If multiple boats are detected → stop and return only a warning.
- Return ONLY valid JSON, no markdown, no explanation.

Return this exact JSON structure:
{
  "boat_name": "string|null",
  "manufacturer": "string|null",
  "model": "string|null",
  "boat_type": "string|null (sailboat/motorboat/catamaran/rib/trawler/sloop/other)",
  "boat_category": "string|null",
  "new_or_used": "string|null (new/used)",
  "year": "number|null",
  "price": "number|null",
  "loa": "string|null (meters)",
  "lwl": "string|null",
  "beam": "string|null",
  "draft": "string|null",
  "air_draft": "string|null",
  "displacement": "string|null",
  "ballast": "string|null",
  "hull_colour": "string|null",
  "hull_construction": "string|null (GRP/steel/aluminum/wood/composite)",
  "hull_type": "string|null (mono/catamaran/trimaran)",
  "hull_number": "string|null",
  "designer": "string|null",
  "builder": "string|null",
  "where": "string|null (shipyard/werf location)",
  "deck_colour": "string|null",
  "deck_construction": "string|null",
  "super_structure_colour": "string|null",
  "super_structure_construction": "string|null",
  "cockpit_type": "string|null",
  "control_type": "string|null",
  "flybridge": "boolean|null",
  "engine_manufacturer": "string|null",
  "engine_model": "string|null",
  "engine_type": "string|null",
  "horse_power": "string|null",
  "hours": "string|null",
  "fuel": "string|null (diesel/petrol/electric/hybrid)",
  "engine_quantity": "string|null",
  "engine_year": "string|null",
  "cruising_speed": "string|null",
  "max_speed": "string|null",
  "drive_type": "string|null",
  "propulsion": "string|null",
  "cabins": "string|null",
  "berths": "string|null",
  "toilet": "string|null",
  "shower": "string|null",
  "bath": "string|null",
  "heating": "boolean|null",
  "air_conditioning": "boolean|null",
  "ce_category": "string|null (A/B/C/D)",
  "passenger_capacity": "number|null",
  "compass": "string|null",
  "gps": "string|null",
  "radar": "string|null",
  "autopilot": "string|null",
  "vhf": "string|null",
  "life_raft": "string|null",
  "epirb": "string|null",
  "fire_extinguisher": "string|null",
  "battery": "string|null",
  "generator": "string|null",
  "solar_panel": "string|null",
  "anchor": "string|null",
  "bimini": "string|null",
  "spray_hood": "string|null",
  "swimming_platform": "string|null",
  "teak_deck": "string|null",
  "television": "string|null",
  "fridge": "string|null",
  "oven": "string|null",
  "microwave": "string|null",
  "owners_comment": "string|null (any visible seller notes)",
  "reg_details": "string|null (registration number/country)",
  "known_defects": "string|null",
  "last_serviced": "string|null",
  "short_description_en": "string|null (generate a 2-3 sentence summary in English)",
  "short_description_nl": "string|null (generate a 2-3 sentence summary in Dutch)",
  "short_description_de": "string|null (generate a 2-3 sentence summary in German)",
  "short_description_fr": "string|null (generate a 2-3 sentence summary in French)",
  "warnings": ["array of strings - missing docs, conflicting data, unreadable text, etc."],
  "confidence": {
    "field_name": 0.0 to 1.0 (confidence score for each extracted field)
  }
}
SCHEMA;

        $parts[] = ['text' => $schema];

        // Add hint text if provided
        $hintText = $request->input('hint_text', '');
        if (!empty($hintText)) {
            $parts[] = ['text' => "Seller hint: \"{$hintText}\""];
        } else {
            $parts[] = ['text' => "No seller hint provided. Extract everything from images only."];
        }

        $parts[] = ['text' => "Now extract all fields from the following images into the JSON schema above:"];

        // Add all images as inline_data
        foreach ($request->file('images') as $image) {
            try {
                $imageData = base64_encode(file_get_contents($image->getRealPath()));
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $image->getMimeType(),
                        'data'      => $imageData
                    ]
                ];
            } catch (\Exception $e) {
                Log::warning("Failed to read image: " . $e->getMessage());
            }
        }

        try {
            $response = Http::timeout(120)->post($endpoint, [
                'contents' => [['parts' => $parts]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature'      => 0.1,
                ],
            ]);

            if (!$response->successful()) {
                Log::error("Gemini extraction failed: " . $response->body());
                return response()->json([
                    'error'   => 'Gemini API request failed',
                    'details' => $response->status()
                ], 500);
            }

            $body = $response->json();
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                return response()->json(['error' => 'Empty response from Gemini'], 500);
            }

            // Parse the JSON response
            $extracted = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try to clean up the response (remove markdown code blocks if present)
                $cleaned = preg_replace('/```json\s*|\s*```/', '', $text);
                $extracted = json_decode($cleaned, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error("Failed to parse Gemini JSON: " . $text);
                    return response()->json([
                        'error'    => 'Failed to parse Gemini response',
                        'raw_text' => $text
                    ], 500);
                }
            }

            return response()->json([
                'success'   => true,
                'extracted' => $extracted,
            ]);

        } catch (\Exception $e) {
            Log::error("Gemini extraction exception: " . $e->getMessage());
            return response()->json([
                'error'   => 'Extraction failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function extractSubmittedTrackableFields(Request $request, array $trackableFields): array
    {
        $ignored = ['_method', '_token', 'availability_rules'];
        $submitted = array_values(array_intersect(
            array_keys($request->except($ignored)),
            $trackableFields
        ));

        if ($request->hasFile('main_image') && in_array('main_image', $trackableFields, true) && !in_array('main_image', $submitted, true)) {
            $submitted[] = 'main_image';
        }

        return array_values(array_unique($submitted));
    }

    private function buildSnapshotForFields(array $source, array $fields): array
    {
        $snapshot = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $source)) {
                $snapshot[$field] = $source[$field];
            }
        }

        return $snapshot;
    }

    private function captureBeforeSnapshot(Yacht $yacht, array $submittedFields, bool $isUpdate): array
    {
        if ($submittedFields === []) {
            return [];
        }

        if (! $isUpdate) {
            return array_fill_keys($submittedFields, null);
        }

        return $this->buildSnapshotForFields($yacht->toArray(), $submittedFields);
    }

    private function buildTrackableFields(array $coreFields, array $booleanFields): array
    {
        $subTableFields = [];
        foreach (Yacht::SUB_TABLE_MAP as $fields) {
            $subTableFields = array_merge($subTableFields, $fields);
        }

        return array_values(array_unique(array_merge(
            $coreFields,
            $booleanFields,
            $subTableFields,
            ['main_image', 'location_id']
        )));
    }

    private function findAiExtraction(Request $request): ?YachtAiExtraction
    {
        $sessionId = trim((string) $request->input('ai_session_id', ''));
        if ($sessionId === '') {
            return null;
        }

        return YachtAiExtraction::query()
            ->where('session_id', $sessionId)
            ->first();
    }

    private function buildFieldLoggingContext(
        Request $request,
        ?User $actor,
        ?YachtAiExtraction $aiExtraction,
        bool $isUpdate
    ): array {
        $requestedSessionId = trim((string) $request->input('ai_session_id', ''));
        $requestedConfidence = $this->extractFieldConfidenceMap($request);
        $extractionConfidence = is_array($aiExtraction?->field_confidence_json) ? $aiExtraction->field_confidence_json : [];
        $fieldConfidence = array_merge($extractionConfidence, $requestedConfidence);
        $fieldCorrectionLabels = $this->extractNormalizedCorrectionLabelMap($request->input('field_correction_labels'));
        $fieldReasons = $this->extractStringMap($request->input('field_reasons'));
        $reason = trim((string) ($request->input('change_reason') ?: $request->input('reason', '')));
        $modelName = trim((string) $request->input('model_name', ''));

        return [
            'changed_by_type' => $this->resolveChangedByType($request),
            'changed_by_id' => $actor?->id,
            'source_type' => $this->normalizeSourceType($request->input('source_type')),
            'field_confidence' => $fieldConfidence,
            'ai_session_id' => $aiExtraction?->session_id ?? ($requestedSessionId !== '' ? $requestedSessionId : null),
            'model_name' => $modelName !== '' ? $modelName : $aiExtraction?->model_name,
            'model_version' => $aiExtraction?->model_version,
            'reason' => $reason !== '' ? $reason : null,
            'correction_label' => $this->normalizeCorrectionLabel($request->input('correction_label')),
            'field_correction_labels' => $fieldCorrectionLabels,
            'field_reasons' => $fieldReasons,
            'scope' => $isUpdate ? 'yacht_update' : 'yacht_create',
            'ai_proposed_values' => is_array($aiExtraction?->normalized_fields_json) ? $aiExtraction->normalized_fields_json : [],
            'ai_field_sources' => is_array($aiExtraction?->field_sources_json) ? $aiExtraction->field_sources_json : [],
        ];
    }

    private function resolveChangedByType(Request $request): string
    {
        $requested = strtolower((string) $request->input('changed_by_type', ''));
        if (in_array($requested, self::ALLOWED_CHANGED_BY_TYPES, true)) {
            return $requested;
        }

        if ($request->filled('ai_session_id')) {
            return 'ai';
        }

        $user = $request->user();
        if ($user && ((method_exists($user, 'isStaff') && $user->isStaff()) || strtolower((string) $user->role) === 'admin')) {
            return 'admin';
        }

        return 'user';
    }

    private function normalizeSourceType(mixed $sourceType): ?string
    {
        $value = strtolower((string) $sourceType);
        if ($value === '' || !in_array($value, self::ALLOWED_SOURCE_TYPES, true)) {
            return null;
        }

        return $value;
    }

    private function normalizeCorrectionLabel(mixed $label): ?string
    {
        $value = strtolower((string) $label);
        if ($value === '' || !in_array($value, self::ALLOWED_CORRECTION_LABELS, true)) {
            return null;
        }

        return $value;
    }

    private function extractFieldConfidenceMap(Request $request): array
    {
        $candidates = [
            $request->input('field_confidence'),
            $request->input('ai_field_confidence'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }

            if (is_string($candidate) && $candidate !== '') {
                $decoded = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    private function extractNormalizedCorrectionLabelMap(mixed $value): array
    {
        $decoded = $this->extractStringMap($value);
        $normalized = [];

        foreach ($decoded as $field => $label) {
            $candidate = $this->normalizeCorrectionLabel($label);
            if ($candidate !== null) {
                $normalized[$field] = $candidate;
            }
        }

        return $normalized;
    }

    private function extractStringMap(mixed $value): array
    {
        if (is_array($value)) {
            return array_filter($value, static fn ($item) => is_scalar($item) && trim((string) $item) !== '');
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_filter($decoded, static fn ($item) => is_scalar($item) && trim((string) $item) !== '');
            }
        }

        return [];
    }
}
