<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Models\YachtImage;
use App\Models\User;
use App\Services\LocationAccessService;
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
    public function __construct(private readonly LocationAccessService $locationAccess)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->visibleYachtsQuery($request->user())
                ->orderBy('boat_name', 'asc')
                ->get()
        );
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

        try {
            DB::beginTransaction();

            $actor = $request->user();
            $isUpdate = $id !== null;
            $yacht = $isUpdate ? Yacht::findOrFail($id) : new Yacht();
            if ($isUpdate) {
                $this->authorizeYachtAccess($actor, $yacht);
            }
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
            $booleanFields = ['allow_bidding'];

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

            if ($request->hasFile('main_image')) {
                if ($isUpdate && $yacht->main_image) {
                    Storage::disk('public')->delete($yacht->main_image);
                }

                $yacht->main_image = $request->file('main_image')->store('yachts/main', 'public');
            }

            if ($request->has('ref_harbor_id')) {
                $requestedHarborId = $request->integer('ref_harbor_id') ?: null;

                if ($actor?->isClient()) {
                    $yacht->ref_harbor_id = $actor->client_location_id;
                } elseif ($actor?->isEmployee()) {
                    if ($requestedHarborId !== null && ! $this->locationAccess->sharesLocation($actor, $requestedHarborId)) {
                        abort(403, 'Forbidden');
                    }

                    $yacht->ref_harbor_id = $requestedHarborId;
                } else {
                    $yacht->ref_harbor_id = $requestedHarborId;
                }
            } elseif (! $isUpdate && $actor?->client_location_id) {
                $yacht->ref_harbor_id = $actor->client_location_id;
            }

            if (! $isUpdate) {
                $yacht->user_id = $actor?->id;

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
            $yacht->saveSubTables($request->all());

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

            DB::commit();

            $yacht->load(['images', 'availabilityRules']);

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
            \Log::warning('Upload debug: no files found', [
                'content_type' => $request->header('Content-Type'),
                'all_keys' => array_keys($request->all()),
                'has_files' => $request->allFiles() ? 'yes' : 'no',
            ]);
            return response()->json(['message' => 'No images detected'], 422);
        }

        $files = is_array($files) ? $files : [$files];
        $uploaded = [];

        foreach ($files as $image) {
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
                    'status'            => 'processing',
                    'enhancement_method'=> 'pending',
                ]);

                // Dispatch Phase 1 processing job
                \App\Jobs\ProcessYachtImageJob::dispatch($record->id);

                $uploaded[] = $record;
            }
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

        return response()->json([
            'message' => "{$updated} images {$request->action}d",
            'count' => $updated,
        ]);
    }

    public function deleteGalleryImage($id): JsonResponse {
        $image = YachtImage::findOrFail($id);
        $yacht = Yacht::findOrFail($image->yacht_id);
        $this->authorizeYachtAccess(Auth::user(), $yacht);
        Storage::disk('public')->delete($image->url);
        $image->delete();
        return response()->json(['message' => 'Image removed']);
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

        foreach ($yacht->images as $img) {
            Storage::disk('public')->delete($img->url);
        }

        $yacht->delete();

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
        $query = Yacht::query()->with(['images', 'availabilityRules']);

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
                $builder->whereIn('ref_harbor_id', $locationIds)
                    ->orWhereHas('owner', function (Builder $ownerQuery) use ($locationIds) {
                        $ownerQuery->whereIn('client_location_id', $locationIds);
                    });
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function resolveYachtLocationId(Yacht $yacht): ?int
    {
        if ($yacht->ref_harbor_id) {
            return (int) $yacht->ref_harbor_id;
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
            'images'    => 'required|array|min:1|max:30',
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
                \Log::warning("Failed to read image: " . $e->getMessage());
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
                \Log::error("Gemini extraction failed: " . $response->body());
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
                    \Log::error("Failed to parse Gemini JSON: " . $text);
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
            \Log::error("Gemini extraction exception: " . $e->getMessage());
            return response()->json([
                'error'   => 'Extraction failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
