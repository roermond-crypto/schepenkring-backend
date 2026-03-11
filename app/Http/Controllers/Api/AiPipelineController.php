<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Yacht;
use App\Services\AiCorrectionLoggingService;
use App\Services\PineconeMatcherService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiPipelineController extends Controller
{
    /**
     * Flat Step 2 schema keys returned by the extraction pipeline.
     */
    private const STEP2_SCHEMA_FIELDS = [
        'boat_name', 'manufacturer', 'model', 'boat_type', 'boat_category', 'new_or_used',
        'year', 'price', 'min_bid_amount', 'vessel_lying', 'location_city', 'status',
        'loa', 'lwl', 'beam', 'draft', 'air_draft', 'displacement', 'ballast',
        'passenger_capacity', 'minimum_height', 'variable_depth', 'max_draft', 'min_draft',
        'designer', 'builder', 'where', 'hull_colour', 'hull_construction', 'hull_number',
        'hull_type', 'super_structure_colour', 'super_structure_construction',
        'deck_colour', 'deck_construction', 'windows', 'cockpit_type', 'control_type', 'flybridge',
        'engine_manufacturer', 'engine_model', 'engine_type', 'horse_power', 'hours', 'fuel',
        'engine_quantity', 'engine_year', 'cruising_speed', 'max_speed', 'drive_type', 'propulsion',
        'tankage', 'gallons_per_hour', 'litres_per_hour', 'engine_location', 'gearbox', 'cylinders',
        'propeller_type', 'starting_type', 'cooling_system', 'engine_serial_number', 'reversing_clutch',
        'transmission', 'motorization_summary', 'fuel_tanks_amount', 'fuel_tank_total_capacity',
        'fuel_tank_material', 'range_km', 'stern_thruster', 'bow_thruster',
        'cabins', 'berths', 'toilet', 'shower', 'bath', 'heating', 'air_conditioning',
        'ce_category', 'ce_max_weight', 'ce_max_motor', 'cvo', 'cbb',
        'interior_type', 'saloon', 'headroom', 'separate_dining_area', 'engine_room',
        'spaces_inside', 'upholstery_color', 'matrasses', 'cushions', 'curtains',
        'berths_fixed', 'berths_extra', 'berths_crew',
        'compass', 'gps', 'radar', 'autopilot', 'vhf', 'plotter', 'ais', 'fishfinder',
        'depth_instrument', 'wind_instrument', 'speed_instrument', 'navigation_lights',
        'log_speed', 'windvane_steering', 'charts_guides', 'rudder_position_indicator',
        'turn_indicator', 'ssb_receiver', 'shortwave_radio', 'short_band_transmitter',
        'weatherfax_navtex', 'satellite_communication',
        'life_raft', 'epirb', 'fire_extinguisher', 'bilge_pump', 'mob_system', 'life_jackets',
        'radar_reflector', 'flares', 'life_buoy', 'bilge_pump_manual', 'bilge_pump_electric',
        'watertight_door', 'gas_bottle_locker', 'self_draining_cockpit',
        'battery', 'battery_charger', 'generator', 'inverter', 'shorepower', 'solar_panel',
        'wind_generator', 'voltage', 'dynamo', 'accumonitor', 'voltmeter', 'shore_power_cable',
        'consumption_monitor', 'control_panel', 'fuel_tank_gauge', 'tachometer',
        'oil_pressure_gauge', 'temperature_gauge',
        'anchor', 'anchor_winch', 'bimini', 'spray_hood', 'swimming_platform', 'swimming_ladder',
        'teak_deck', 'cockpit_table', 'dinghy', 'trailer', 'television', 'oven', 'microwave',
        'fridge', 'freezer', 'cooker', 'cd_player', 'dvd_player', 'satellite_reception',
        'covers', 'fenders', 'cooking_fuel', 'hot_air', 'stove', 'central_heating',
        'water_tank', 'water_tank_material', 'water_tank_gauge', 'water_maker',
        'waste_water_tank', 'waste_water_tank_material', 'waste_water_tank_gauge',
        'waste_water_tank_drainpump', 'deck_suction', 'water_system', 'hot_water',
        'sea_water_pump', 'deck_wash_pump', 'deck_shower',
        'anchor_connection', 'stern_anchor', 'spud_pole', 'cockpit_tent', 'outdoor_cushions',
        'sea_rails', 'pushpit_pullpit', 'sail_lowering_system', 'crutch', 'dinghy_brand',
        'outboard_engine', 'crane', 'davits', 'oars_paddles',
        'spinnaker', 'gennaker', 'sailplan_type', 'number_of_masts', 'spars_material',
        'bowsprit', 'standing_rig', 'sail_surface_area', 'stabilizer_sail', 'sail_amount',
        'sail_material', 'sail_manufacturer', 'genoa', 'main_sail', 'furling_mainsail',
        'tri_sail', 'storm_jib', 'mizzen', 'furling_mizzen', 'jib', 'roller_furling_foresail',
        'genoa_reefing_system', 'flying_jib', 'halfwinder_bollejan', 'winches',
        'electric_winches', 'manual_winches', 'hydraulic_winches', 'self_tailing_winches',
        'reg_details', 'known_defects', 'last_serviced', 'owners_comment',
        'short_description_en', 'short_description_nl', 'short_description_de',
    ];

    /**
     * Required fields — if any of these are null after Stage 1, enrichment triggers.
     */
    private const REQUIRED_FIELDS = ['boat_name', 'year', 'loa', 'hull_type'];

    /**
     * Confidence threshold — below this, enrichment triggers.
     */
    private const CONFIDENCE_THRESHOLD = 0.70;

    /**
     * Optional equipment fields that must be conservative yes/no/unknown only.
     */
    private const OPTIONAL_EQUIPMENT_FIELDS = [
        'life_jackets', 'bimini', 'anchor', 'fishfinder', 'bow_thruster', 'stern_thruster',
        'trailer', 'heating', 'toilet', 'fridge', 'freezer', 'ais', 'life_raft',
        'fire_extinguisher', 'bilge_pump', 'solar_panel', 'swimming_platform',
        'swimming_ladder', 'teak_deck', 'cockpit_table', 'dinghy', 'television',
        'oven', 'microwave', 'spinnaker', 'gennaker', 'epirb', 'mob_system',
        'radar_reflector', 'flares', 'wind_generator', 'shorepower', 'inverter',
        'battery_charger', 'depth_instrument', 'wind_instrument', 'speed_instrument',
        'navigation_lights', 'radar', 'autopilot', 'gps', 'vhf', 'plotter', 'compass',
        'water_tank', 'waste_water_tank', 'bath', 'shower'
    ];

    /**
     * Sources considered inferred (not direct visual/text evidence).
     */
    private const INFERRED_SOURCES = [
        'gemini_db_enrichment',
        'openai_world_knowledge_enrichment',
        'openai_fast_fill',
        'pinecone_database',
        'pinecone_override',
    ];

    /**
     * Fields used by Step 2 assistant suggestions.
     */
    private const SUGGESTION_TARGET_FIELDS = [
        'year',
        'loa',
        'beam',
        'draft',
        'engine_manufacturer',
        'fuel',
        'engine_quantity',
        'horse_power',
        'price',
    ];

    /**
     * POST /api/ai/pipeline-extract
     *
     * Multi-stage AI Fill Pipeline:
     *   Stage 1: Gemini Vision extraction (images + hint → structured JSON)
     *   Gate:    Check confidence + required fields
     *   Stage 2: Gemini enrichment with DB fleet data as RAG context (only if gate fails)
     *   Return:  Unified step2_form_values + meta
     */
    public function extractAndEnrich(
        Request $request,
        PineconeMatcherService $pineconeMatcher,
        AiCorrectionLoggingService $correctionLogging
    ): JsonResponse
    {
        set_time_limit(480); // 8 minutes
        ini_set('memory_limit', '1024M'); // 1GB for processing base64 images

        $request->validate([
            'images'    => 'required_without:yacht_id|array|max:30',
            'images.*'  => 'image|max:10240',
            'yacht_id'  => 'required_without:images|integer|exists:yachts,id',
            'hint_text' => 'nullable|string|max:2000',
            'speed_mode' => 'nullable|string|in:fast,balanced,deep',
        ]);

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return response()->json(['error' => 'GEMINI_API_KEY not configured'], 500);
        }

        $speedMode = strtolower((string) $request->input('speed_mode', 'balanced'));
        $stagesRun = [];
        $formValues = $this->buildEmptyFormValues();
        $fieldConfidence = [];
        $fieldSources = [];
        $warnings = [];
        $needsConfirmation = [];
        $visionImagesUsed = 0;

        // ─── STAGE 0: Local Text Parsing (always runs, no API) ────────
        $hintText = $request->input('hint_text');
        if (!empty($hintText)) {
            $localParsed = $this->runLocalTextParse($hintText);
            if (!empty($localParsed)) {
                $stagesRun[] = 'local_text_parse';
                foreach ($localParsed as $key => $value) {
                    if ($value !== null) {
                        $formValues[$key] = $value;
                        $fieldConfidence[$key] = 0.75; // local parse is reliable for what it finds
                        $fieldSources[$key] = 'local_text_parse';
                    }
                }
                Log::info('[AI Pipeline] Stage 0 (local parse) extracted ' . count(array_filter($localParsed, fn($v) => $v !== null)) . ' fields');
            }
        }

        // ─── PARALLEL EXECUTION TRACKS ────────────────────────────────
        // We run independent stages in parallel to hit the 60s target.
        // Track 1: Gemini Vision (Images + Hint)
        // Track 2: Pinecone Pre-emptive Match (Hint only)
        // Track 3: OpenAI Pre-emptive Enrich (Hint only)

        $responses = Http::pool(function ($pool) use ($apiKey, $request, $hintText) {
            // Gemini Vision call
            $payload = $this->prepareGeminiVisionPayload($request, $apiKey);
            if ($payload) {
                $pool->as('gemini_vision')->withHeaders($payload['headers'])
                    ->timeout($payload['timeout'])
                    ->post($payload['url'], $payload['body']);
            }

            // OpenAI Enrichment (based on hint text)
            if (!empty($hintText)) {
                $openAiKey = config('services.openai.key');
                if ($openAiKey) {
                    $pool->as('openai_enrich')->withHeaders(['Authorization' => 'Bearer ' . $openAiKey])
                        ->timeout(45)
                        ->post('https://api.openai.com/v1/chat/completions', [
                            'model' => 'gpt-4o-mini',
                            'messages' => [
                                ['role' => 'system', 'content' => $this->getOpenAiEnrichmentPrompt($hintText)],
                                ['role' => 'user', 'content' => 'Extract boat specs from hint.']
                            ],
                            'response_format' => ['type' => 'json_object'],
                        ]);
                }
            }

            // Pinecone Embedding (Stage 1 of Pinecone)
            if (!empty($hintText)) {
                $openAiKey = config('services.openai.key');
                if ($openAiKey) {
                    $pool->as('pinecone_embed')->withToken($openAiKey)
                        ->timeout(15)
                        ->post('https://api.openai.com/v1/embeddings', [
                            'model' => 'text-embedding-3-small',
                            'input' => $hintText,
                            'dimensions' => 1408,
                        ]);
                }
            }
        });

        // ─── PROCESS RESULTS ──────────────────────────────────────────
        $feedResult = [
            'consensus_values' => [],
            'field_confidence' => [],
            'field_sources' => [],
            'top_matches' => [],
            'warnings' => [],
        ];
        
        // 1. Process Gemini Vision
        $visionResult = $responses['gemini_vision'] ?? null;
        if ($visionResult && $visionResult->successful()) {
            $stagesRun[] = 'gemini_vision';
            $visionImagesUsed = count($visionResult->json()['candidates'][0]['content']['parts'] ?? []) - 1; // Correcting count
             // Note: parts[0] is text prompt, the rest are images
            $extracted = $visionResult->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($extracted) {
                $extracted = $this->cleanGeminiJson($extracted);
                $geminiData = json_decode($extracted, true);
                if ($geminiData) {
                    $geminiValues = $this->buildFormValues($geminiData);
                    $geminiConfidence = $geminiData['confidence'] ?? [];
                    foreach ($geminiValues as $key => $value) {
                        if ($value !== null) {
                            $formValues[$key] = $value;
                            $fieldConfidence[$key] = $geminiConfidence[$key] ?? 0.80;
                            $fieldSources[$key] = 'gemini_vision';
                        }
                    }
                }
            }
        }

        // 2. Process OpenAI Enrichment
        $openaiResult = $responses['openai_enrich'] ?? null;
        if ($openaiResult && $openaiResult->successful()) {
            $stagesRun[] = 'openai_enrich_parallel';
            $enriched = $openaiResult->json()['choices'][0]['message']['content'] ?? null;
            if ($enriched) {
                $enrichedData = json_decode($enriched, true);
                if ($enrichedData) {
                    foreach ($enrichedData as $key => $val) {
                        if ($val !== null && (!isset($formValues[$key]) || $formValues[$key] === null)) {
                            $formValues[$key] = $val;
                            $fieldConfidence[$key] = 0.60;
                            $fieldSources[$key] = 'openai_enrich_parallel';
                        }
                    }
                }
            }
        }

        // 3. Process Pinecone (Stage 2: Query)
        $pineconeEmbed = $responses['pinecone_embed'] ?? null;
        if ($pineconeEmbed && $pineconeEmbed->successful()) {
            $vector = $pineconeEmbed->json('data.0.embedding');
            if ($vector) {
                $feedResult = $pineconeMatcher->queryWithVector($vector);
                if (!empty($feedResult['consensus_values'])) {
                    $stagesRun[] = 'pinecone_match_parallel';
                    foreach ($feedResult['consensus_values'] as $field => $value) {
                        $this->mergeConsensusValue($field, $value, $feedResult['field_confidence'][$field] ?? 0.90, 'pinecone_match_parallel', $formValues, $fieldConfidence, $fieldSources, $needsConfirmation);
                    }
                }
            }
        }

        // ─── CONFIDENCE GATE ──────────────────────────────────────────
        $overallConfidence = $this->computeOverallConfidence($fieldConfidence);
        $missingRequired = $this->findMissingRequired($formValues);

        // ─── STAGE 2: Local Database Lookup (FAST) ───────────────────
        // We still run this locally because it's nearly instant (<50ms).
        $databaseResult = $this->runDatabaseCatalogConsensus($formValues, $hintText);
        if (!empty($databaseResult['consensus_values'])) {
            $stagesRun[] = 'database_catalog_consensus';
            foreach ($databaseResult['consensus_values'] as $field => $value) {
                $this->mergeConsensusValue($field, $value, $databaseResult['field_confidence'][$field] ?? 0.82, 'database_catalog', $formValues, $fieldConfidence, $fieldSources, $needsConfirmation);
            }
        }

        $overallConfidence = $this->computeOverallConfidence($fieldConfidence);
        $missingRequired = $this->findMissingRequired($formValues);

        // ─── FAST PATH & HIGH-CONFIDENCE SKIP ────────────────────────
        $pineconeFieldCount = count($feedResult['consensus_values'] ?? []);
        $pineconeMatchCount = count($feedResult['top_matches'] ?? []);
        $hasAllRequired = empty($missingRequired);
        $highConfidence = $overallConfidence >= 0.90; // Higher threshold for auto-skip
        $visionRan = in_array('gemini_vision', $stagesRun, true);
        $filledFieldCount = count(array_filter($formValues, fn($v) => $v !== null && $v !== '' && $v !== 'unknown'));

        // Decision logic: skip slow stages ONLY if:
        //   1. Vision actually ran successfully (if it failed, we NEED enrichment to compensate)
        //   2. Speed mode is 'fast', OR high confidence + all required + pinecone match
        // When vision fails, always run the full slow path — matching old project behavior.
        $useFastPath = $visionRan && (
            $speedMode === 'fast'
            || ($speedMode !== 'deep' && $hasAllRequired && $highConfidence && $pineconeMatchCount >= 1 && $filledFieldCount >= 20)
        );

        if ($useFastPath) {
            Log::info('[AI Pipeline] OPTIMIZATION: Skipping slow validation stages', [
                'speed_mode' => $speedMode,
                'overall_confidence' => $overallConfidence,
                'pinecone_fields' => $pineconeFieldCount
            ]);
            $stagesRun[] = 'confidence_optimization_skip';

            // Still run a quick OpenAI backfill if required fields are missing
            if (!$hasAllRequired) {
                $openAiKeyEnv = config('services.openai.key');
                if (!empty($openAiKeyEnv)) {
                    $openaiEnriched = $this->runOpenAiEnrichment($formValues, $fieldConfidence, $openAiKeyEnv, $missingRequired);
                    if (!empty($openaiEnriched)) {
                        $stagesRun[] = 'openai_fast_fill';
                        foreach ($openaiEnriched as $key => $val) {
                            if ($val !== null && (!isset($formValues[$key]) || $formValues[$key] === null)) {
                                $formValues[$key] = $val;
                                $fieldConfidence[$key] = 0.50;
                                $fieldSources[$key] = 'openai_fast_fill';
                            }
                        }
                    }
                }
            }

            $pineconeResult = ['similar_boats' => [], 'anomalies' => [], 'anomaly_fields' => []];
            $removedFields = [];
            $mergeResult = [
                'form_values'        => $formValues,
                'field_confidence'   => $fieldConfidence,
                'removed_fields'     => [],
                'needs_confirmation' => [],
                'validation_notes'   => 'Optimized flow: High confidence or Pinecone match allowed skipping slow validation.',
            ];

        } else {
            // ─── SLOW PATH: Full enrichment + validation pipeline ─────────
            
            // Try Gemini DB enrichment
            $geminiEnriched = $this->runEnrichment($formValues, $fieldConfidence, $apiKey);
            if (!empty($geminiEnriched)) {
                $stagesRun[] = 'gemini_db_enrichment';
                $gemConf = $geminiEnriched['confidence'] ?? [];
                unset($geminiEnriched['confidence'], $geminiEnriched['warnings']);

                foreach ($geminiEnriched as $key => $val) {
                    if ($val !== null && (!isset($formValues[$key]) || $formValues[$key] === null)) {
                        $formValues[$key] = $val;
                        $fieldConfidence[$key] = $gemConf[$key] ?? 0.60;
                        $fieldSources[$key] = 'gemini_db_enrichment';
                    }
                }
            }

            // Try OpenAI World Knowledge for remaining
            $openAiKeyEnv = config('services.openai.key');
            if (!empty($openAiKeyEnv)) {
                $openaiEnriched = $this->runOpenAiEnrichment($formValues, $fieldConfidence, $openAiKeyEnv, []);
                if (!empty($openaiEnriched)) {
                    $stagesRun[] = 'openai_world_knowledge_enrichment';
                    $oaConf = $openaiEnriched['confidence'] ?? [];
                    unset($openaiEnriched['confidence'], $openaiEnriched['warnings']);

                    foreach ($openaiEnriched as $key => $val) {
                        if ($val !== null && (!isset($formValues[$key]) || $formValues[$key] === null)) {
                            $formValues[$key] = $val;
                            $fieldConfidence[$key] = $oaConf[$key] ?? 0.50;
                            $fieldSources[$key] = 'openai_world_knowledge_enrichment';
                        }
                    }
                }
            }

            // Cross-Validation
            $pineconeResult = $this->runPineconeCrossValidation($formValues, $fieldConfidence);
            if (!empty($pineconeResult['similar_boats'])) $stagesRun[] = 'pinecone_cross_validation';

            // ChatGPT Validation
            $validationResult = $this->runChatGptValidation($formValues, $fieldConfidence, $pineconeResult, $feedResult, $databaseResult, $request);
            if (!empty($validationResult['confirmed_fields']) || !empty($validationResult['removed_fields'])) {
                $stagesRun[] = 'chatgpt_validation';
            }

            // Merge
            $mergeResult = $this->mergeWithConfidence($formValues, $fieldConfidence, $validationResult, $pineconeResult);
            $formValues      = $mergeResult['form_values'];
            $fieldConfidence  = $mergeResult['field_confidence'];
            $removedFields    = $mergeResult['removed_fields'];
            $needsConfirmation = array_values(array_unique(array_merge($needsConfirmation, $mergeResult['needs_confirmation'])));
            $overallConfidence = $this->computeOverallConfidence($fieldConfidence);
        }
 // end slow path

        Log::info('[AI Pipeline] Pipeline complete', [
            'stages'              => $stagesRun,
            'total_fields_filled' => count(array_filter($formValues, fn($v) => $v !== null)),
            'fields_removed'      => count($removedFields),
            'needs_confirmation'  => $needsConfirmation,
            'anomalies_detected'  => count($pineconeResult['anomalies']),
            'overall_confidence'  => $overallConfidence,
        ]);

        $normalization = $this->normalizeOptionalEquipmentFields($formValues, $fieldConfidence, $fieldSources);
        $formValues = $normalization['form_values'];
        $fieldConfidence = $normalization['field_confidence'];
        $fieldSources = $normalization['field_sources'];
        if (!empty($normalization['warnings'])) {
            $warnings = array_values(array_unique(array_merge($warnings, $normalization['warnings'])));
        }

        $meta = [
            'overall_confidence'      => round($overallConfidence, 2),
            'field_confidence'        => $fieldConfidence,
            'field_sources'           => $fieldSources,
            'needs_user_confirmation' => array_values(array_unique($needsConfirmation)),
            'removed_fields'          => $removedFields,
            'anomalies'               => $pineconeResult['anomalies'],
            'validation_notes'        => $mergeResult['validation_notes'],
            'stages_run'              => $stagesRun,
            'warnings'                => $warnings,
            'similar_boats_count'     => count($pineconeResult['similar_boats']),
            'feed_matches_count'      => count($feedResult['top_matches'] ?? []),
            'database_matches_count'  => count($databaseResult['top_matches'] ?? []),
            'speed_mode'              => $speedMode,
            'vision_images_used'      => $visionImagesUsed,
            'model_name'              => 'gemini-2.5-flash',
        ];

        try {
            $extraction = $correctionLogging->createExtraction([
                'yacht_id' => $request->integer('yacht_id') ?: null,
                'user_id' => $request->user()?->id,
                'status' => 'completed',
                'model_name' => 'gemini-2.5-flash',
                'model_version' => 'gemini-2.5-flash',
                'hint_text' => $hintText,
                'image_count' => $visionImagesUsed,
                'raw_output_json' => [
                    'step2_form_values' => $formValues,
                    'meta' => $meta,
                ],
                'normalized_fields_json' => $formValues,
                'field_confidence_json' => $fieldConfidence,
                'field_sources_json' => $fieldSources,
                'meta_json' => [
                    'stages_run' => $stagesRun,
                    'warnings' => $warnings,
                    'speed_mode' => $speedMode,
                ],
                'extracted_at' => now(),
            ]);

            $meta['ai_session_id'] = $extraction->session_id;
        } catch (\Throwable $e) {
            Log::warning('[AI Pipeline] Failed to persist extraction session', [
                'error' => $e->getMessage(),
            ]);
            $meta['ai_session_id'] = null;
        }

        // Trigger batch AI enhancement for all yacht images in the background
        $yachtId = $request->integer('yacht_id');
        if ($yachtId) {
            \App\Models\YachtImage::where('yacht_id', $yachtId)
                ->whereIn('status', ['approved', 'ready_for_review'])
                ->where('enhancement_method', '!=', 'cloudinary')
                ->each(function ($image) {
                    \App\Jobs\EnhanceYachtImageJob::dispatch($image->id)->delay(now()->addSeconds(2));
                });
        }

        // ── FINAL SANITIZATION: Strip ALL "unknown" values ──────────────
        // AI models sometimes ignore prompt instructions and return "unknown".
        // This hard filter guarantees "unknown" never reaches the frontend.
        foreach ($formValues as $field => $value) {
            if (is_string($value) && strtolower(trim($value)) === 'unknown') {
                $formValues[$field] = null;
            }
        }

        return response()->json([
            'success' => true,
            'step2_form_values' => $formValues,
            'meta' => $meta,
        ]);
    }

    /**
     * Stage 1: Send images + hint to Gemini 2.5 Flash for structured extraction.
     */
    private function runGeminiVisionExtract(Request $request, string $apiKey): array
    {
        $model    = "gemini-2.5-flash";
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $speedMode = strtolower((string) $request->input('speed_mode', 'balanced'));
        $maxVisionImages = match ($speedMode) {
            'fast' => (int) config('services.ai_pipeline.max_vision_images_fast', 6),
            'deep' => (int) config('services.ai_pipeline.max_vision_images_deep', 16),
            default => (int) config('services.ai_pipeline.max_vision_images_balanced', 10),
        };
        $maxVisionImages = max(1, $maxVisionImages);

        $parts = [];

        $schema = $this->getGeminiSchema();

        $parts[] = ['text' => $schema];

        $parts[] = ['text' => "STRICT VISUAL ANALYSIS: Examine each image carefully. Report ONLY what you can directly SEE or READ. Do NOT guess or infer from general boat knowledge."];

        // Add all images as inline_data FIRST
        // Priority: DB images first (higher quality, already processed), then FormData fallback
        // This matches the old project's behavior for reliability.
        $imageCount = 0;

        if ($request->has('yacht_id')) {
            $yachtId = $request->input('yacht_id');
            $images = \App\Models\YachtImage::where('yacht_id', $yachtId)
                ->whereIn('status', ['approved', 'ready_for_review', 'processing', 'uploaded'])
                ->orderBy('sort_order')
                ->limit($maxVisionImages)
                ->get();
            foreach ($images as $yachtImage) {
                try {
                    $fullPath = $this->resolveStoredImagePath($yachtImage);
                    if (!$fullPath || !file_exists($fullPath)) {
                        continue;
                    }

                    $imageData = base64_encode(file_get_contents($fullPath));
                    $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';
                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data'      => $imageData,
                        ]
                    ];
                    $imageCount++;
                } catch (\Exception $e) {
                    Log::warning("[AI Pipeline] Failed to read db image: " . $e->getMessage());
                }
            }
        }

        if ($imageCount === 0 && $request->hasFile('images')) {
            foreach (array_slice($request->file('images'), 0, $maxVisionImages) as $image) {
                try {
                    $imageData = base64_encode(file_get_contents($image->getRealPath()));
                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => $image->getMimeType(),
                            'data'      => $imageData,
                        ]
                    ];
                    $imageCount++;
                } catch (\Exception $e) {
                    Log::warning("[AI Pipeline] Failed to read uploaded image: " . $e->getMessage());
                }
            }
        }

        if ($imageCount === 0) {
            return ['error' => 'No valid images found for analysis'];
        }

        // Hint text comes LAST (Gemini gives highest weight to final context)
        $hintText = $request->input('hint_text', '');
        if (!empty($hintText)) {
            $parts[] = ['text' => <<<HINT
SELLER-PROVIDED TEXT DATA:
"{$hintText}"

Extract data from this text into JSON fields. Text-sourced data gets confidence 0.90.
For fields NOT mentioned in text, ONLY fill if clearly visible in images.

🚨 CONSERVATIVE DETECTION RULE for equipment (e.g. life jackets, bimini, etc.):
- IF clearly visible or explicitly written in text -> "yes"
- IF clearly absent or text says "no" -> "no"
- IF unsure or not visible/mentioned -> "unknown"
DO NOT guess "yes" or "no" for equipment if evidence is missing.
HINT];
        } else {
            $parts[] = ['text' => "No seller text provided. Extract ONLY what is visible in the images above. 🚨 CONSERVATIVE DETECTION RULE: For equipment, if unsure, return null, NOT yes/no."];
        }

        try {
            // Keep stage-1 bounded so the request does not exceed frontend/API gateway limits.
            $maxRetries = match ($speedMode) {
                'fast' => 1,
                'deep' => 2,
                default => 2,
            };
            $requestTimeout = match ($speedMode) {
                'fast' => 35,
                'deep' => 60,
                default => 45,
            };
            $response = null;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $response = Http::timeout($requestTimeout)->post($endpoint, [
                        'contents' => [['parts' => $parts]],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                            'temperature'      => 0.1,
                        ],
                    ]);
                } catch (\Throwable $requestError) {
                    if ($attempt < $maxRetries) {
                        $waitSeconds = $attempt; // 1s, 2s
                        Log::warning("[AI Pipeline] Gemini request exception, retrying in {$waitSeconds}s (attempt {$attempt}/{$maxRetries}): " . $requestError->getMessage());
                        sleep($waitSeconds);
                        continue;
                    }

                    return ['error' => 'Extraction failed: ' . $requestError->getMessage()];
                }

                if ($response->successful()) {
                    break;
                }

                if ($response->status() === 429 && $attempt < $maxRetries) {
                    $waitSeconds = match ($attempt) { 1 => 5, 2 => 10, default => 15 };
                    Log::warning("[AI Pipeline] Gemini 429 rate limit, retrying in {$waitSeconds}s (attempt {$attempt}/{$maxRetries})");
                    sleep($waitSeconds);
                    continue;
                }

                Log::error("[AI Pipeline] Gemini Stage 1 failed: " . $response->body());
                return ['error' => 'Gemini API request failed (status ' . $response->status() . ')'];
            }

            $body = $response->json();
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                return ['error' => 'Empty response from Gemini'];
            }

            $extracted = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $cleaned = preg_replace('/```json\s*|\s*```/', '', $text);
                $extracted = json_decode($cleaned, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error("[AI Pipeline] Failed to parse Gemini JSON: " . $text);
                    return ['error' => 'Failed to parse Gemini response'];
                }
            }

            return [
                'extracted' => $extracted,
                'image_count' => $imageCount,
            ];

        } catch (\Exception $e) {
            Log::error("[AI Pipeline] Gemini Stage 1 exception: " . $e->getMessage());
            return ['error' => 'Extraction failed: ' . $e->getMessage()];
        }
    }

    /**
     * Stage 2: Enrichment — query DB for similar yachts, then ask Gemini to fill gaps.
     */
    private function runEnrichment(array $currentValues, array $fieldConfidence, string $apiKey): ?array
    {
        try {
            // Build search query from whatever we already know
            $searchTerms = array_filter([
                $currentValues['manufacturer'] ?? null,
                $currentValues['model'] ?? null,
                $currentValues['boat_type'] ?? null,
                $currentValues['boat_name'] ?? null,
            ]);

            // Query DB for similar boats
            $query = Yacht::query()
                ->whereNotNull('boat_name')
                ->limit(5);

            if (!empty($currentValues['manufacturer'])) {
                $query->where(function ($q) use ($currentValues) {
                    $q->where('manufacturer', 'LIKE', '%' . $currentValues['manufacturer'] . '%')
                      ->orWhere('boat_name', 'LIKE', '%' . $currentValues['manufacturer'] . '%')
                      ->orWhereHas('construction', function ($q2) use ($currentValues) {
                          $q2->where('builder', 'LIKE', '%' . $currentValues['manufacturer'] . '%');
                      });
                });
            } elseif (!empty($currentValues['boat_type'])) {
                $query->where('boat_type', $currentValues['boat_type']);
            }

            $similarBoats = $query->get();

            if ($similarBoats->isEmpty()) {
                // Try broader search if no matches
                $similarBoats = Yacht::whereNotNull('boat_name')
                    ->inRandomOrder()
                    ->limit(5)
                    ->get();
            }

            if ($similarBoats->isEmpty()) {
                Log::info('[AI Pipeline] No fleet data available for enrichment');
                return null;
            }

            // Build fleet context string
            $fleetContext = $similarBoats->map(function ($yacht) {
                // Since $yacht->toArray() merges sub-tables in our updated model,
                // we can just use the flattened properties directly or through the relationships.
                $specs = array_filter([
                    $yacht->boat_name ? "Name: {$yacht->boat_name}" : null,
                    $yacht->manufacturer ? "Make: {$yacht->manufacturer}" : null,
                    $yacht->model ? "Model: {$yacht->model}" : null,
                    $yacht->year ? "Year: {$yacht->year}" : null,
                    $yacht->dimensions?->loa ?? $yacht->loa ? "LOA: " . ($yacht->dimensions?->loa ?? $yacht->loa) : null,
                    $yacht->dimensions?->beam ?? $yacht->beam ? "Beam: " . ($yacht->dimensions?->beam ?? $yacht->beam) : null,
                    $yacht->construction?->hull_type ?? $yacht->hull_type ? "Hull: " . ($yacht->construction?->hull_type ?? $yacht->hull_type) : null,
                    $yacht->construction?->hull_construction ?? $yacht->hull_construction ? "Construction: " . ($yacht->construction?->hull_construction ?? $yacht->hull_construction) : null,
                    $yacht->engine?->engine_manufacturer ?? $yacht->engine_manufacturer ? "Engine: " . ($yacht->engine?->engine_manufacturer ?? $yacht->engine_manufacturer) : null,
                    $yacht->engine?->fuel ?? $yacht->fuel ? "Fuel: " . ($yacht->engine?->fuel ?? $yacht->fuel) : null,
                    $yacht->accommodation?->cabins ?? $yacht->cabins ? "Cabins: " . ($yacht->accommodation?->cabins ?? $yacht->cabins) : null,
                    $yacht->accommodation?->berths ?? $yacht->berths ? "Berths: " . ($yacht->accommodation?->berths ?? $yacht->berths) : null,
                    $yacht->boat_type ? "Type: {$yacht->boat_type}" : null,
                ]);
                return implode(', ', $specs);
            })->implode("\n");

            // Build the current partial data as context
            $partialData = json_encode(
                array_filter($currentValues, fn($v) => $v !== null),
                JSON_PRETTY_PRINT
            );

            // Build null fields list
            $nullFields = array_keys(array_filter($currentValues, fn($v) => $v === null));
            $nullFieldsList = implode(', ', $nullFields);

            $optionalFields = implode(', ', self::OPTIONAL_EQUIPMENT_FIELDS);
            $enrichPrompt = <<<PROMPT
You are a boat data enrichment expert with deep knowledge of yacht and boat specifications.
You have partial data about a boat (from image analysis) and a database of similar boats for reference.

Your goal is to fill AS MANY null fields as possible.

RULES:
- Fill ALL null/missing fields using BOTH the reference data AND your world knowledge about this boat make/model.
- If you know the manufacturer and model, use your knowledge of that specific model's standard specifications.
- Use reference boats to infer LIKELY values (e.g. if all similar boats have diesel engines, this one probably does too).
- For equipment fields (GPS, compass, radar, fridge, oven, etc.) — if they are STANDARD on this type/size of boat, set them to "Yes" or provide a typical brand.
- For boolean fields (heating, air_conditioning) — infer from the boat type and size.
- NEVER contradict existing non-null values.
- Mark inferred values with confidence 0.50-0.65.
- ONLY leave a field null if you truly have no basis to fill it.
- Return ONLY valid JSON with the same field names.

PARTIAL DATA (from vision analysis):
{$partialData}

FIELDS STILL NULL (fill as many as possible):
{$nullFieldsList}

REFERENCE BOATS FROM DATABASE:
{$fleetContext}

Return JSON with ONLY the fields you can fill (the currently-null ones). Include a "confidence" object and a "warnings" array. Do not return fields that already have values.
PROMPT;

            $model    = "gemini-2.5-flash";
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            // Retry logic for Gemini 429 rate limits
            $response = null;
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $response = Http::timeout(45)->post($endpoint, [
                    'contents' => [['parts' => [['text' => $enrichPrompt]]]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature'      => 0.2,
                    ],
                ]);

                if ($response->successful()) break;

                if ($response->status() === 429 && $attempt < 3) {
                    $wait = pow(2, $attempt);
                    Log::warning("[AI Pipeline] Enrichment Gemini 429, retrying in {$wait}s (attempt {$attempt}/3)");
                    sleep($wait);
                    continue;
                }

                Log::warning('[AI Pipeline] Enrichment API failed: ' . $response->status());
                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            if (!$text) return null;

            $enriched = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $cleaned = preg_replace('/```json\s*|\s*```/', '', $text);
                $enriched = json_decode($cleaned, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('[AI Pipeline] Failed to parse enrichment JSON');
                    return null;
                }
            }

            return $enriched;

        } catch (\Exception $e) {
            Log::error('[AI Pipeline] Database Enrichment exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Stage 3: OpenAI GPT-4o Enrichment — use world knowledge to fill remaining gaps.
     */
    private function runOpenAiEnrichment(array $currentValues, array $fieldConfidence, string $apiKey, array $missingImportant): ?array
    {
        try {
            // Check if we have ANY context at all to work with
            $hasAnyContext = !empty($currentValues['manufacturer']) 
                          || !empty($currentValues['model'])
                          || !empty($currentValues['boat_name'])
                          || !empty($currentValues['boat_type'])
                          || !empty($currentValues['short_description_en']);
            if (!$hasAnyContext) {
                Log::info('[AI Pipeline] Not enough data for OpenAI enrichment');
                return null;
            }

            $partialData = json_encode(
                array_filter($currentValues, fn($v) => $v !== null),
                JSON_PRETTY_PRINT
            );

            $nullFields = array_keys(array_filter($currentValues, fn($v) => $v === null));
            $nullFieldsList = implode(', ', $nullFields);

            $systemPrompt = <<<PROMPT
You are a marine data enrichment expert with DEEP, comprehensive knowledge of yacht and boat specifications, makes, models, and standard equipment.

Given partial data about a boat, your CRITICAL task is to fill in AS MANY missing fields as possible using your extensive world knowledge.

RULES:
- FILL EVERY FIELD YOU POSSIBLY CAN. Do NOT leave fields null if you have any reasonable basis to fill them.
- NEVER contradict or overwrite existing values.
- If you can identify the exact make/model, provide ALL standard specifications for that model (dimensions, engine, equipment, etc.).
- For equipment fields (GPS, radar, compass, fridge, oven, etc.): if that equipment is STANDARD on this type/size of boat, set to "Yes".
- For boolean fields (heating, air_conditioning, flybridge): infer from the boat type, size, and class.
- For dimension fields (loa, beam, draft): if you know the model, provide the factory specifications.
- For engine fields: provide typical engine specs for this model if known.
- For comfort fields (cabins, berths, toilet, shower): provide typical layout for this model.
- Generate short_description_nl (Dutch translation of the English description) and short_description_de (German translation) if missing.
- Generate short_description_nl (Dutch translation of the English description), short_description_de (German translation), and short_description_fr (French translation) if missing.
- Return ONLY valid JSON containing the fields you can fill, plus "confidence" object and "warnings" array.

PARTIAL DATA ALREADY KNOWN:
{$partialData}

FIELDS TO TRY TO FILL (fill as many as possible):
{$nullFieldsList}
PROMPT;

            $endpoint = 'https://api.openai.com/v1/chat/completions';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(45)->post($endpoint, [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => 'Please fill in the missing data for this boat in JSON format.']
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2, // Low temperature for factual data
            ]);

            if (!$response->successful()) {
                Log::warning('[AI Pipeline] OpenAI Enrichment API failed: ' . $response->status() . ' - ' . $response->body());
                return null;
            }

            $body = $response->json();
            $text = $body['choices'][0]['message']['content'] ?? null;

            if (!$text) return null;

            $enriched = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // OpenAI often wraps JSON in markdown blocks, try stripping them
                $cleaned = preg_replace('/```json\s*|\s*```/', '', $text);
                $enriched = json_decode($cleaned, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('[AI Pipeline] Failed to parse OpenAI enrichment JSON', ['raw_text' => $text]);
                    return null;
                }
            }

            return $enriched;

        } catch (\Exception $e) {
            Log::error('[AI Pipeline] OpenAI Enrichment exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Stage 0: Local Text Parsing — regex-based extraction from hint text.
     * No API calls needed. Always works instantly.
     */
    private function runLocalTextParse(string $text): array
    {
        $result = [];
        $lower = mb_strtolower($text);

        // ── Known Boat Manufacturers ──────────────────────────────────
        $manufacturers = [
            'Jeanneau', 'Beneteau', 'Bavaria', 'Hanse', 'Hallberg-Rassy', 'Dehler',
            'Dufour', 'Lagoon', 'Fountaine Pajot', 'Leopard', 'Catana', 'Nautitech',
            'Sunseeker', 'Princess', 'Fairline', 'Azimut', 'Ferretti', 'Riva',
            'Pershing', 'Itama', 'Absolute', 'Cranchi', 'Sessa', 'Fiart',
            'Contest', 'Oyster', 'Swan', 'Baltic', 'Wauquiez', 'Moody',
            'Westerly', 'Southerly', 'Rustler', 'Malo', 'Najad', 'HR',
            'Solemar', 'Zodiac', 'Brig', 'Ribeye', 'Williams', 'Highfield',
            'Boston Whaler', 'Grady-White', 'Robalo', 'Sea Ray', 'Chaparral',
            'Monterey', 'Four Winns', 'Regal', 'Cobalt', 'Chris-Craft',
            'Nimbus', 'Axopar', 'XO Boats', 'Parker', 'Quicksilver',
            'Mercury', 'Yamaha', 'Honda Marine', 'Suzuki Marine', 'Evinrude',
            'Volvo Penta', 'MAN', 'Cummins', 'Caterpillar', 'Yanmar',
            'Perkins', 'Steyr', 'Nanni', 'Beta Marine', 'Vetus',
            'Grand Soleil', 'X-Yachts', 'J Boats', 'Bali', 'Excess',
            'Privilege', 'Outremer', 'Neel', 'Corsair', 'Dragonfly',
            'Benetti', 'Sanlorenzo', 'Mangusta', 'Maiora', 'Aicon',
            'Galeon', 'Greenline', 'Sealine', 'Prestige', 'Jeanneau',
            'Fjord', 'Windy', 'Goldfish', 'Saxdor', 'Rand', 'Pardo',
            'De Antonio', 'Invictus', 'Capelli', 'Nuova Jolly', 'Joker Boat',
            'Lomac', 'Scanner', 'Ranieri', 'Salpa', 'BWA',
            'Linssen', 'Pedro', 'Aquanaut', 'Valk', 'Boarncruiser',
            'Vripack', 'Jongert', 'Royal Huisman', 'Feadship', 'Heesen',
            'Amels', 'Oceanco', 'Lurssen', 'Blohm Voss',
            'Nordhavn', 'Fleming', 'Grand Banks', 'Kadey-Krogen', 'Selene',
            'Riviera', 'Maritimo', 'Palm Beach', 'Hinckley', 'Sabre',
            'Back Cove', 'MJM', 'Regulator', 'Yellowfin', 'Contender',
            'Bertram', 'Viking', 'Cabo', 'Hatteras', 'Luhrs',
            'Tiara', 'Pursuit', 'Scout', 'Sportsman', 'Tidewater',
            'Wellcraft', 'Trophy', 'Starcraft', 'Crownline', 'Glastron',
            'Bayliner', 'Maxum', 'Larson', 'Rinker', 'Formula',
            'Glacier Bay', 'World Cat', 'Pro-Kat', 'Twin Vee',
            'SeaVee', 'Mag Bay', 'HCB', 'Invincible', 'Freeman',
            'Sealine', 'Hardy', 'Duchy', 'Orkney', 'Quicksilver',
        ];

        // Try matching manufacturer + model from text
        foreach ($manufacturers as $mfr) {
            // Match manufacturer followed by model text — stop at comma, semicolon, period, newline, or dash followed by space
            $pattern = '/\b(' . preg_quote($mfr, '/') . ')\s+([^,;\.\r\n–—]+)/iu';
            if (preg_match($pattern, $text, $match)) {
                $result['manufacturer'] = trim($match[1]);
                $modelRaw = trim($match[2]);
                // Clean model — remove trailing common words and years
                $modelRaw = preg_replace('/\s*\b(19[6-9]\d|20[0-3]\d)\b.*$/i', '', $modelRaw);
                $modelRaw = preg_replace('/\s+(for\s+sale|price|year|built|from|with|in|excellent|good|new|used|diesel|petrol|gas|full|eu|vat).*$/i', '', $modelRaw);
                $modelRaw = trim($modelRaw);
                // Limit model to max ~50 chars (prevent capturing paragraphs)
                if (strlen($modelRaw) > 50) {
                    $modelRaw = substr($modelRaw, 0, 50);
                    $modelRaw = preg_replace('/\s+\S*$/', '', $modelRaw); // trim to last full word
                }
                if (!empty($modelRaw)) {
                    $result['model'] = $modelRaw;
                    $result['boat_name'] = trim($match[1] . ' ' . $modelRaw);
                }
                break;
            }
        }

        // ── Year (4-digit number between 1960-2030) ──────────────────
        if (preg_match('/\b(19[6-9]\d|20[0-3]\d)\b/', $text, $m)) {
            $result['year'] = (int)$m[1];
        }

        // ── Price (€, EUR, USD, $, or just a large number) ───────────
        if (preg_match('/(?:€|EUR|eur)\s*([\d\.,]+)/i', $text, $m)) {
            $result['price'] = (float)str_replace([',', '.'], ['', ''], $m[1]);
        } elseif (preg_match('/(?:\$|USD|usd)\s*([\d\.,]+)/i', $text, $m)) {
            $result['price'] = (float)str_replace([',', '.'], ['', ''], $m[1]);
        } elseif (preg_match('/price\s*[:=]?\s*([\d\.,]+)/i', $text, $m)) {
            $result['price'] = (float)str_replace([',', '.'], ['', ''], $m[1]);
        } elseif (preg_match('/\b(\d{4,7})\b/', $text, $m) && !isset($result['year'])) {
            // Only if no year matched, try to find price-like numbers (skip small numbers)
            $num = (int)$m[1];
            if ($num > 10000 && $num < 9999999) {
                $result['price'] = $num;
            }
        }

        // ── LOA / Length ─────────────────────────────────────────────
        if (preg_match('/(?:loa|length|lengte|length overall)\s*[:=]?\s*([\d\.,]+)\s*(?:m|meter|ft|feet)?/i', $text, $m)) {
            $result['loa'] = str_replace(',', '.', $m[1]);
        }

        // ── Beam / Width ─────────────────────────────────────────────
        if (preg_match('/(?:beam|breedte|width|breadth)\s*[:=]?\s*([\d\.,]+)\s*(?:m|meter|ft|feet)?/i', $text, $m)) {
            $result['beam'] = str_replace(',', '.', $m[1]);
        }

        // ── Draft ────────────────────────────────────────────────────
        if (preg_match('/(?:draft|diepgang|draught)\s*[:=]?\s*([\d\.,]+)\s*(?:m|meter|ft|feet)?/i', $text, $m)) {
            $result['draft'] = str_replace(',', '.', $m[1]);
        }

        // ── Cabins ───────────────────────────────────────────────────
        if (preg_match('/(\d+)\s*(?:cabin|cabins|cabine|cabines|kajuit|kajuiten)/i', $text, $m)) {
            $result['cabins'] = $m[1];
        }

        // ── Berths ───────────────────────────────────────────────────
        if (preg_match('/(\d+)\s*(?:berth|berths|slaapplaats|slaapplaatsen)/i', $text, $m)) {
            $result['berths'] = $m[1];
        }

        // ── Toilet / Head ────────────────────────────────────────────
        if (preg_match('/(\d+)\s*(?:toilet|toilets|head|heads|wc)/i', $text, $m)) {
            $result['toilet'] = $m[1];
        }

        // ── Shower ───────────────────────────────────────────────────
        if (preg_match('/(\d+)\s*(?:shower|showers|douche|douches)/i', $text, $m)) {
            $result['shower'] = $m[1];
        }

        // ── Fuel Type ────────────────────────────────────────────────
        if (preg_match('/\b(diesel|petrol|gasoline|electric|hybrid)\b/i', $text, $m)) {
            $result['fuel'] = strtolower($m[1]);
            if ($result['fuel'] === 'gasoline') $result['fuel'] = 'petrol';
        }

        // ── Boat Type ────────────────────────────────────────────────
        $boatTypes = [
            'sailboat' => ['sailboat', 'sailing yacht', 'sailing boat', 'zeilboot', 'zeiljacht', 'sloop'],
            'motorboat' => ['motor yacht', 'motorboat', 'motor boat', 'motorjacht', 'motorkruiser', 'powerboat'],
            'catamaran' => ['catamaran', 'cat', 'multihull'],
            'rib' => ['rib', 'rigid inflatable'],
            'trawler' => ['trawler'],
            'sloop' => ['sloop'],
        ];
        foreach ($boatTypes as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($lower, $kw) !== false) {
                    $result['boat_type'] = $type;
                    break 2;
                }
            }
        }
        // Try to infer from known model names
        if (!isset($result['boat_type']) && isset($result['manufacturer'])) {
            $sailMakers = ['jeanneau', 'beneteau', 'bavaria', 'hanse', 'hallberg-rassy', 'dehler', 'dufour', 'contest', 'oyster', 'swan', 'najad', 'x-yachts', 'j boats', 'grand soleil'];
            $motorMakers = ['sunseeker', 'princess', 'fairline', 'azimut', 'ferretti', 'riva', 'pershing', 'absolute', 'cranchi', 'sessa', 'sea ray', 'axopar'];
            $catMakers = ['lagoon', 'fountaine pajot', 'leopard', 'catana', 'nautitech', 'bali', 'excess'];
            $ribMakers = ['solemar', 'zodiac', 'brig', 'ribeye', 'williams', 'highfield', 'capelli', 'nuova jolly', 'joker boat', 'lomac', 'scanner'];
            
            $mfrLower = strtolower($result['manufacturer']);
            if (in_array($mfrLower, $sailMakers)) $result['boat_type'] = 'sailboat';
            elseif (in_array($mfrLower, $motorMakers)) $result['boat_type'] = 'motorboat';
            elseif (in_array($mfrLower, $catMakers)) $result['boat_type'] = 'catamaran';
            elseif (in_array($mfrLower, $ribMakers)) $result['boat_type'] = 'rib';
        }

        // ── New or Used ──────────────────────────────────────────────
        if (preg_match('/\b(new|nieuw|brand\s*new)\b/i', $lower)) {
            $result['new_or_used'] = 'new';
        } elseif (preg_match('/\b(used|occasion|tweedehands|second\s*hand)\b/i', $lower)) {
            $result['new_or_used'] = 'used';
        } elseif (isset($result['year']) && $result['year'] < (int)date('Y')) {
            $result['new_or_used'] = 'used';
        }

        // ── Horse Power ──────────────────────────────────────────────
        if (preg_match('/(\d+)\s*(?:hp|pk|horse\s*power|CV)/i', $text, $m)) {
            $result['horse_power'] = $m[1] . ' HP';
        }

        // ── Engine Hours ─────────────────────────────────────────────
        if (preg_match('/(\d+)\s*(?:hours?|hrs?|uren|draaiuren|engine\s*hours)/i', $text, $m)) {
            $result['hours'] = $m[1];
        }

        // ── Max Speed ────────────────────────────────────────────────
        if (preg_match('/(?:max\s*speed|top\s*speed|max)\s*[:=]?\s*([\d\.,]+)\s*(?:kn|knots|knopen|kts)?/i', $text, $m)) {
            $result['max_speed'] = str_replace(',', '.', $m[1]) . ' kn';
        }

        // ── Cruising Speed ───────────────────────────────────────────
        if (preg_match('/(?:cruising\s*speed|cruise)\s*[:=]?\s*([\d\.,]+)\s*(?:kn|knots|kts)?/i', $text, $m)) {
            $result['cruising_speed'] = str_replace(',', '.', $m[1]) . ' kn';
        }

        // ── Hull Type ────────────────────────────────────────────────
        if (stripos($lower, 'catamaran') !== false || stripos($lower, 'multihull') !== false) {
            $result['hull_type'] = 'catamaran';
        } elseif (stripos($lower, 'trimaran') !== false) {
            $result['hull_type'] = 'trimaran';
        } else {
            $result['hull_type'] = 'mono'; // Default for most boats
        }

        // ── Hull Material ────────────────────────────────────────────
        $hullMaterials = ['GRP' => ['grp', 'fiberglass', 'fibreglass', 'polyester', 'glasvezel'],
                          'steel' => ['steel', 'staal'],
                          'aluminum' => ['aluminum', 'aluminium', 'alu'],
                          'wood' => ['wood', 'wooden', 'hout', 'houten', 'mahogany', 'teak hull'],
                          'composite' => ['composite', 'carbon', 'kevlar', 'epoxy']];
        foreach ($hullMaterials as $mat => $kws) {
            foreach ($kws as $kw) {
                if (stripos($lower, $kw) !== false) {
                    $result['hull_construction'] = $mat;
                    break 2;
                }
            }
        }

        // ── Equipment Detection (keyword matching) ───────────────────
        $equipment = [
            'gps' => ['gps', 'chartplotter gps', 'garmin', 'raymarine', 'simrad', 'navico', 'lowrance', 'furuno'],
            'radar' => ['radar'],
            'autopilot' => ['autopilot', 'auto pilot', 'autohelm'],
            'vhf' => ['vhf', 'marifoon'],
            'plotter' => ['plotter', 'chartplotter', 'chart plotter', 'kaartplotter'],
            'bimini' => ['bimini', 'bimini top'],
            'spray_hood' => ['spray hood', 'sprayhood', 'spraykap'],
            'swimming_platform' => ['swim platform', 'swimming platform', 'zwemplatform', 'bathing platform'],
            'swimming_ladder' => ['swim ladder', 'swimming ladder', 'zwemtrap'],
            'teak_deck' => ['teak deck', 'teakdek'],
            'bow_thruster' => ['bow thruster', 'boegschroef'],
            'generator' => ['generator', 'genset'],
            'air_conditioning' => ['air conditioning', 'airco', 'a/c', 'airconditioning', 'klimaat'],
            'heating' => ['heating', 'verwarming', 'central heating', 'webasto', 'eberspacher'],
            'solar_panel' => ['solar', 'solar panel', 'zonnepaneel', 'zonnepanelen'],
            'anchor_winch' => ['anchor winch', 'windlass', 'ankerlier'],
            'cockpit_table' => ['cockpit table', 'cockpittafel'],
            'television' => ['television', 'tv', 'flat screen'],
            'microwave' => ['microwave', 'magnetron'],
            'fridge' => ['fridge', 'refrigerator', 'koelkast', 'icebox'],
            'freezer' => ['freezer', 'vriezer', 'deep freeze'],
            'oven' => ['oven'],
            'cooker' => ['cooker', 'stove', 'fornuis', 'kooktoestel', 'gas stove'],
            'spinnaker' => ['spinnaker', 'gennaker', 'code 0', 'asymmetric'],
            'dinghy' => ['dinghy', 'tender', 'bijboot'],
            'life_raft' => ['life raft', 'liferaft', 'reddingsvlot'],
            'epirb' => ['epirb'],
            'shorepower' => ['shore power', 'shorepower', 'walstroom'],
            'inverter' => ['inverter', 'omvormer'],
        ];

        foreach ($equipment as $field => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($lower, $kw) !== false) {
                    // For boolean fields use true, for string fields use 'Yes'
                    if (in_array($field, ['heating', 'air_conditioning'])) {
                        $result[$field] = true;
                    } else {
                        $result[$field] = 'Yes';
                    }
                    break;
                }
            }
        }

        // ── CE Category ──────────────────────────────────────────────
        if (preg_match('/\bCE\s*(?:category|cat\.?)?\s*([A-D])\b/i', $text, $m)) {
            $result['ce_category'] = strtoupper($m[1]);
        }

        // ── Engine Quantity ──────────────────────────────────────────
        if (preg_match('/\b(single|1)\s*engine/i', $text)) {
            $result['engine_quantity'] = '1';
        } elseif (preg_match('/\b(twin|2|double|dual)\s*(engine|outboard|inboard|motor)/i', $text)) {
            $result['engine_quantity'] = '2';
        } elseif (preg_match('/\b(triple|3)\s*(engine|outboard|inboard|motor)/i', $text)) {
            $result['engine_quantity'] = '3';
        }

        // ── Engine Type ──────────────────────────────────────────────
        if (stripos($lower, 'outboard') !== false) {
            $result['engine_type'] = 'outboard';
        } elseif (stripos($lower, 'inboard') !== false) {
            $result['engine_type'] = 'inboard';
        } elseif (stripos($lower, 'saildrive') !== false) {
            $result['engine_type'] = 'saildrive';
        } elseif (stripos($lower, 'sterndrive') !== false || stripos($lower, 'stern drive') !== false) {
            $result['engine_type'] = 'sterndrive';
        }

        // ── Known Engine Manufacturers ───────────────────────────────
        $engineMfrs = ['Volvo Penta', 'Yanmar', 'Mercury', 'Suzuki', 'Yamaha', 'Honda',
                       'Caterpillar', 'CAT', 'MAN', 'Cummins', 'Perkins', 'Nanni',
                       'Beta Marine', 'Vetus', 'Steyr', 'Evinrude', 'Tohatsu'];
        foreach ($engineMfrs as $eng) {
            if (stripos($text, $eng) !== false) {
                $result['engine_manufacturer'] = $eng;
                break;
            }
        }

        // ── Fuel type from engine context ────────────────────────────
        if (!isset($result['fuel'])) {
            if (stripos($lower, 'petrol') !== false || stripos($lower, 'gasoline') !== false || stripos($lower, 'benzine') !== false) {
                $result['fuel'] = 'petrol';
            } elseif (stripos($lower, 'diesel') !== false) {
                $result['fuel'] = 'diesel';
            }
        }

        // ── Infer cabin/comfort from description keywords ────────────
        if (!isset($result['cabins'])) {
            if (preg_match('/\b(\d+)\s*(cabin|cabins|cabine|cabines)/i', $text, $m)) {
                $result['cabins'] = $m[1];
            } elseif (stripos($lower, 'cabin') !== false || stripos($lower, 'cabine') !== false) {
                $result['cabins'] = '1';
            }
        }
        if (!isset($result['toilet'])) {
            if (stripos($lower, 'bathroom') !== false || stripos($lower, 'head') !== false || stripos($lower, 'toilet') !== false || stripos($lower, 'wc') !== false) {
                $result['toilet'] = '1';
            }
        }
        if (!isset($result['shower'])) {
            if (stripos($lower, 'bathroom') !== false || stripos($lower, 'shower') !== false || stripos($lower, 'douche') !== false) {
                $result['shower'] = '1';
            }
        }

        // ── Infer hull construction from boat type ──────────────────
        if (!isset($result['hull_construction'])) {
            $ribTypes = ['rib', 'rigid inflatable'];
            foreach ($ribTypes as $rt) {
                if (stripos($lower, $rt) !== false) {
                    $result['hull_construction'] = 'GRP';
                    break;
                }
            }
        }

        // ── Infer swimming platform from description ────────────────
        if (!isset($result['swimming_platform'])) {
            if (stripos($lower, 'swimming') !== false || stripos($lower, 'sunbathing') !== false || stripos($lower, 'bathing') !== false) {
                $result['swimming_platform'] = 'Yes';
            }
        }

        // ── Infer cooker/galley from description ────────────────────
        if (!isset($result['cooker'])) {
            if (stripos($lower, 'galley') !== false || stripos($lower, 'kitchen') !== false || stripos($lower, 'keuken') !== false || stripos($lower, 'kombuis') !== false) {
                $result['cooker'] = 'Yes';
            }
        }
        if (!isset($result['fridge'])) {
            if (stripos($lower, 'galley') !== false) {
                $result['fridge'] = 'Yes';
            }
        }

        // ── Infer new_or_used from context ───────────────────────────
        if (!isset($result['new_or_used'])) {
            if (stripos($lower, 'brand new') !== false || stripos($lower, 'nieuw') !== false) {
                $result['new_or_used'] = 'new';
            } elseif (isset($result['year']) && $result['year'] < (int)date('Y')) {
                $result['new_or_used'] = 'used';
            }
        }

        // ── Hardtop / Bimini ────────────────────────────────────────
        if (!isset($result['bimini'])) {
            if (stripos($lower, 'hardtop') !== false || stripos($lower, 'hard top') !== false || stripos($lower, 't-top') !== false) {
                $result['bimini'] = 'Hardtop';
            }
        }

        // ── Cockpit type ────────────────────────────────────────────
        if (!isset($result['cockpit_type'])) {
            if (stripos($lower, 'open cockpit') !== false) {
                $result['cockpit_type'] = 'Open';
            } elseif (stripos($lower, 'center console') !== false || stripos($lower, 'centre console') !== false) {
                $result['cockpit_type'] = 'Center console';
            }
        }

        // ── Control type ────────────────────────────────────────────
        if (!isset($result['control_type'])) {
            if (stripos($lower, 'wheel') !== false || stripos($lower, 'helm') !== false) {
                $result['control_type'] = 'Wheel';
            } elseif (stripos($lower, 'tiller') !== false) {
                $result['control_type'] = 'Tiller';
            }
        }

        // ── Drive type ──────────────────────────────────────────────
        if (!isset($result['drive_type'])) {
            if (stripos($lower, 'outboard') !== false) {
                $result['drive_type'] = 'Outboard';
            } elseif (stripos($lower, 'shaft') !== false) {
                $result['drive_type'] = 'Shaft drive';
            } elseif (stripos($lower, 'jet') !== false) {
                $result['drive_type'] = 'Jet drive';
            }
        }

        // ── Deep-V hull type ────────────────────────────────────────
        if (stripos($lower, 'deep-v') !== false || stripos($lower, 'deep v') !== false) {
            $result['hull_type'] = 'mono';
        }

        // ── Propulsion ──────────────────────────────────────────────
        if (!isset($result['propulsion'])) {
            if (stripos($lower, 'propeller') !== false || stripos($lower, 'prop') !== false) {
                $result['propulsion'] = 'Propeller';
            } elseif (stripos($lower, 'jet') !== false) {
                $result['propulsion'] = 'Jet';
            } elseif (stripos($lower, 'sail') !== false) {
                $result['propulsion'] = 'Sail';
            }
        }

        // ── Short Description ────────────────────────────────────────
        $result['short_description_en'] = $text;

        return $result;
    }

    /**
     * Get the JSON schema structure expected by the pipeline.
     */
    private function getGeminiSchema(): string
    {
        $optionalFields = implode(', ', self::OPTIONAL_EQUIPMENT_FIELDS);

        return <<<SCHEMA
You are an EXPERT YACHT DATA EXTRACTION AGENT. You extract data from boat images and seller-provided text.

🚨 TARGET: Fill AS MANY FIELDS AS POSSIBLE while maintaining high accuracy.
- If the boat is a WELL-KNOWN MODEL (e.g., Jeanneau Sun Odyssey 40, Bavaria 37, Beneteau Oceanis 350) and you have identified it with >95% confidence from text/logos, you ARE ALLOWED to infer standard factory specifications (LOA, beam, draft, displacement, fuel tankage, engine manufacturer) even if not explicitly visible.
- For unique or custom features, stick to visible evidence.

🚨 ANTI-HALLUCINATION RULES:
1. Colors: Report exactly what you see.
2. Boat type: Identify as sailboat/motorboat/catamaran based on visible features.
3. Equipment (Life jackets, bimini, anchor, etc.): 
   - set to "yes" ONLY if clearly visible or mentioned in text.
   - set to "no" ONLY if clearly absent.
   - set to null IF unsure or not visible. NEVER GUESS for equipment. DO NOT use the word "Unknown" or "unknown".

🚨 LANGUAGE RULES:
- All descriptive text values (colors, categories, materials, etc.) MUST be returned in Dutch (Nederlands).
- Do NOT return English words for values like "White" (use "Wit"), "Steel" (use "Staal"), etc.

🚨 CONFIDENCE RULES:
- Identified from text/labels: 0.95
- Identified from seller hint: 0.90
- Clearly visible object: 0.85
- Strong factory inference (known model + catalog knowledge): 0.85
- Partially visible/obscured: 0.75
- Below 0.75 confidence: set to null.

Return EXACTLY this JSON structure:
{
  "boat_name": "string (Full name + model, e.g. 'Blue Moon - Jeanneau Sun Odyssey 40')",
  "manufacturer": "string|null",
  "model": "string|null",
  "boat_type": "string|null (sailboat/motorboat/catamaran/rib/trawler/sloop/other)",
  "boat_category": "string|null",
  "new_or_used": "string|null (new/used)",
  "year": "number|null",
  "price": "number|null",
  "min_bid_amount": "number|null",
  "vessel_lying": "string|null",
  "location_city": "string|null",
  "status": "string|null",

  "// dimensions": "Technical measurements",
  "loa": "string|null (meters)",
  "lwl": "string|null",
  "beam": "string|null (meters)",
  "draft": "string|null (meters)",
  "air_draft": "string|null (meters)",
  "displacement": "string|null (kg or tons)",
  "ballast": "string|null",
  "passenger_capacity": "number|null",
  "minimum_height": "string|null",
  "variable_depth": "string|null",
  "max_draft": "string|null",
  "min_draft": "string|null",

  "// construction": "Hull and structural details",
  "designer": "string|null",
  "builder": "string|null",
  "where": "string|null (where built)",
  "hull_colour": "string|null",
  "hull_construction": "string|null (GRP/steel/aluminum/wood/composite)",
  "hull_number": "string|null",
  "hull_type": "string|null (mono/catamaran/trimaran)",
  "super_structure_colour": "string|null",
  "super_structure_construction": "string|null",
  "deck_colour": "string|null",
  "deck_construction": "string|null",
  "windows": "string|null",
  "cockpit_type": "string|null (Aft cockpit/Center cockpit/Open)",
  "control_type": "string|null (Wheel/Tiller/Joystick)",
  "flybridge": "boolean|null",

  "// engines": "Propulsion and engine room",
  "engine_manufacturer": "string|null (e.g. Volvo Penta, Yanmar)",
  "engine_model": "string|null",
  "engine_type": "string|null (Inboard/Outboard/Saildrive)",
  "horse_power": "string|null",
  "hours": "string|null",
  "fuel": "string|null (diesel/petrol/electric/hybrid)",
  "engine_quantity": "string|null",
  "engine_year": "string|null",
  "cruising_speed": "string|null",
  "max_speed": "string|null",
  "drive_type": "string|null (Shaft/Saildrive/Outdrive)",
  "propulsion": "string|null (Propeller/Jet/Sail)",
  "tankage": "string|null (e.g. 2000L)",
  "gallons_per_hour": "string|null",
  "litres_per_hour": "string|null",
  "engine_location": "string|null",
  "gearbox": "string|null",
  "cylinders": "string|null",
  "propeller_type": "string|null",
  "starting_type": "string|null",
  "cooling_system": "string|null",
  "engine_serial_number": "string|null",
  "reversing_clutch": "string|null",
  "transmission": "string|null",
  "motorization_summary": "string|null",
  "fuel_tanks_amount": "string|null",
  "fuel_tank_total_capacity": "string|null",
  "fuel_tank_material": "string|null",
  "range_km": "string|null",
  "stern_thruster": "string|null (yes/no/null)",
  "bow_thruster": "string|null (yes/no/null)",

  "// accommodation": "Interior and living spaces",
  "cabins": "string|null",
  "berths": "string|null",
  "toilet": "string|null (yes/no/null)",
  "shower": "string|null (yes/no/null)",
  "bath": "string|null (yes/no/null)",
  "interior_type": "string|null",
  "saloon": "string|null",
  "headroom": "string|null",
  "separate_dining_area": "string|null",
  "engine_room": "string|null",
  "spaces_inside": "string|null",
  "upholstery_color": "string|null",
  "matrasses": "string|null",
  "cushions": "string|null",
  "curtains": "string|null",
  "berths_fixed": "string|null",
  "berths_extra": "string|null",
  "berths_crew": "string|null",

  "// navigation": "Electronics and navigation suite",
  "compass": "string|null",
  "gps": "string|null",
  "radar": "string|null",
  "autopilot": "string|null",
  "vhf": "string|null",
  "plotter": "string|null",
  "ais": "string|null (yes/no/null)",
  "fishfinder": "string|null (yes/no/null)",
  "depth_instrument": "string|null",
  "wind_instrument": "string|null",
  "speed_instrument": "string|null",
  "navigation_lights": "string|null",
  "log_speed": "string|null",
  "windvane_steering": "string|null",
  "charts_guides": "string|null",
  "rudder_position_indicator": "string|null",
  "turn_indicator": "string|null",
  "ssb_receiver": "string|null",
  "shortwave_radio": "string|null",
  "short_band_transmitter": "string|null",
  "weatherfax_navtex": "string|null",
  "satellite_communication": "string|null",

  "// safety": "Safety equipment",
  "life_raft": "string|null (yes/no/null)",
  "epirb": "string|null (yes/no/null)",
  "fire_extinguisher": "string|null (yes/no/null)",
  "bilge_pump": "string|null (yes/no/null)",
  "mob_system": "string|null (yes/no/null)",
  "life_jackets": "string|null (yes/no/null)",
  "radar_reflector": "string|null (yes/no/null)",
  "flares": "string|null (yes/no/null)",
  "life_buoy": "string|null (yes/no/null)",
  "bilge_pump_manual": "string|null (yes/no/null)",
  "bilge_pump_electric": "string|null (yes/no/null)",
  "watertight_door": "string|null (yes/no/null)",
  "gas_bottle_locker": "string|null (yes/no/null)",
  "self_draining_cockpit": "string|null (yes/no/null)",

  "// electrical": "Electrical systems",
  "battery": "string|null",
  "battery_charger": "string|null",
  "generator": "string|null",
  "inverter": "string|null",
  "shorepower": "string|null (yes/no/null)",
  "solar_panel": "string|null (yes/no/null)",
  "wind_generator": "string|null (yes/no/null)",
  "voltage": "string|null",
  "dynamo": "string|null",
  "accumonitor": "string|null",
  "voltmeter": "string|null",
  "shore_power_cable": "string|null",
  "consumption_monitor": "string|null",
  "control_panel": "string|null",
  "fuel_tank_gauge": "string|null",
  "tachometer": "string|null",
  "oil_pressure_gauge": "string|null",
  "temperature_gauge": "string|null",

  "// deck_comfort": "Deck equipment and comfort features",
  "anchor": "string|null (yes/no/null)",
  "anchor_winch": "string|null (Electric/Manual/None)",
  "bimini": "string|null (yes/no/null)",
  "spray_hood": "string|null (yes/no/null)",
  "swimming_platform": "string|null (yes/no/null)",
  "swimming_ladder": "string|null (yes/no/null)",
  "teak_deck": "string|null (yes/no/null)",
  "cockpit_table": "string|null (yes/no/null)",
  "dinghy": "string|null (yes/no/null)",
  "trailer": "string|null (yes/no/null)",
  "television": "string|null (yes/no/null)",
  "oven": "string|null (yes/no/null)",
  "microwave": "string|null (yes/no/null)",
  "fridge": "string|null (yes/no/null)",
  "freezer": "string|null (yes/no/null)",
  "cooker": "string|null (Electric/Gas/Petrol/Alcohol)",
  "cd_player": "string|null",
  "dvd_player": "string|null",
  "satellite_reception": "string|null",
  "covers": "string|null",
  "fenders": "string|null",
  "cooking_fuel": "string|null",
  "hot_air": "string|null",
  "stove": "string|null",
  "central_heating": "string|null",
  "water_tank": "string|null",
  "water_tank_material": "string|null",
  "water_tank_gauge": "string|null",
  "water_maker": "string|null",
  "waste_water_tank": "string|null",
  "waste_water_tank_material": "string|null",
  "waste_water_tank_gauge": "string|null",
  "waste_water_tank_drainpump": "string|null",
  "deck_suction": "string|null",
  "water_system": "string|null",
  "hot_water": "string|null",
  "sea_water_pump": "string|null",
  "deck_wash_pump": "string|null",
  "deck_shower": "string|null",
  "anchor_connection": "string|null",
  "stern_anchor": "string|null",
  "spud_pole": "string|null",
  "cockpit_tent": "string|null",
  "outdoor_cushions": "string|null",
  "sea_rails": "string|null",
  "pushpit_pullpit": "string|null",
  "sail_lowering_system": "string|null",
  "crutch": "string|null",
  "dinghy_brand": "string|null",
  "outboard_engine": "string|null",
  "crane": "string|null",
  "davits": "string|null",
  "oars_paddles": "string|null",

  "// rigging": "Sail and rigging details",
  "spinnaker": "string|null (yes/no/null)",
  "gennaker": "string|null (yes/no/null)",
  "sailplan_type": "string|null",
  "number_of_masts": "string|null",
  "spars_material": "string|null",
  "bowsprit": "string|null",
  "standing_rig": "string|null",
  "sail_surface_area": "string|null",
  "stabilizer_sail": "string|null",
  "sail_amount": "string|null",
  "sail_material": "string|null",
  "sail_manufacturer": "string|null",
  "genoa": "string|null",
  "main_sail": "string|null",
  "furling_mainsail": "string|null",
  "tri_sail": "string|null",
  "storm_jib": "string|null",
  "mizzen": "string|null",
  "furling_mizzen": "string|null",
  "jib": "string|null",
  "roller_furling_foresail": "string|null",
  "genoa_reefing_system": "string|null",
  "flying_jib": "string|null",
  "halfwinder_bollejan": "string|null",
  "winches": "string|null",
  "electric_winches": "string|null",
  "manual_winches": "string|null",
  "hydraulic_winches": "string|null",
  "self_tailing_winches": "string|null",

  "// registry": "Registration and legal",
  "reg_details": "string|null",
  "known_defects": "string|null",
  "last_serviced": "string|null",
  "short_description_en": "string (2-3 sentence summary based ONLY on confirmed data)",
  "short_description_nl": "string (Dutch translation of the English summary)",
  "short_description_de": "string (German translation of the English summary)",
  "short_description_fr": "string (French translation of the English summary)",
  "warnings": ["array of strings — flag uncertain detections, contradictions between images, unreadable text"],
  "confidence": {
    "field_name": "number"
  }
}
SCHEMA;
    }

    /**
     * Build clean form values from Gemini response (strip meta keys).
     */
    private function buildFormValues(array $extracted): array
    {
        $metaKeys = ['warnings', 'confidence', 'reasoning', 'reasoning_notes'];
        $values = [];

        foreach ($extracted as $key => $value) {
            if (in_array($key, $metaKeys)) continue;
            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Enforce conservative tri-state output for optional equipment fields.
     */
    private function normalizeOptionalEquipmentFields(
        array $formValues,
        array $fieldConfidence,
        array $fieldSources
    ): array {
        $warnings = [];

        foreach (self::OPTIONAL_EQUIPMENT_FIELDS as $field) {
            $raw = $formValues[$field] ?? null;
            $normalized = $this->coerceOptionalEquipmentValue($raw);

            if ($normalized === null) {
                $formValues[$field] = null;
                continue;
            }

            $source = (string) ($fieldSources[$field] ?? '');
            $confidence = (float) ($fieldConfidence[$field] ?? 0.0);

            // We no longer conservatively downgrade to unknown.
            // We trust the enrichment stages (Gemini/OpenAI/Pinecone) to provide the best possible guess.
            // If it's unknown, it's unknown, but we don't force it.
            $formValues[$field] = $normalized;
        }

        return [
            'form_values' => $formValues,
            'field_confidence' => $fieldConfidence,
            'field_sources' => $fieldSources,
            'warnings' => $warnings,
        ];
    }

    private function coerceOptionalEquipmentValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_int($value) || is_float($value)) {
            return $value > 0 ? 'yes' : 'no';
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '' || in_array($normalized, ['null', 'n/a', 'na'], true)) {
            return null;
        }

        if (in_array($normalized, ['unknown', 'unsure', 'uncertain', 'not sure', 'maybe'], true)) {
            return null;
        }

        if (in_array($normalized, ['yes', 'y', 'true', 'present', 'included', 'installed', 'available'], true)) {
            return 'yes';
        }

        if (in_array($normalized, ['no', 'n', 'false', 'absent', 'not installed', 'not available', 'none'], true)) {
            return 'no';
        }

        if (preg_match('/\b(with|equipped|included|installed)\b/i', $value)) {
            return 'yes';
        }

        if (preg_match('/\b(without|absent|not\s+visible|not\s+present)\b/i', $value)) {
            return 'no';
        }

        if (preg_match('/\d+/', $value)) {
            return 'yes';
        }

        return null;
    }

    private function isInferredSource(?string $source): bool
    {
        if ($source === null || $source === '') {
            return true;
        }

        return in_array($source, self::INFERRED_SOURCES, true);
    }

    /**
     * Compute overall confidence from per-field confidence scores.
     */
    private function computeOverallConfidence(array $fieldConfidence): float
    {
        if (empty($fieldConfidence)) return 0.0;

        $scores = array_filter($fieldConfidence, fn($v) => is_numeric($v));
        if (empty($scores)) return 0.0;

        return array_sum($scores) / count($scores);
    }

    /**
     * Find required fields that are still null/empty.
     */
    private function findMissingRequired(array $formValues): array
    {
        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            if (($formValues[$field] ?? null) === null || $formValues[$field] === '') {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    // ═══════════════════════════════════════════════════════════════════
    // NEW VALIDATION PIPELINE METHODS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Stage 2.5: Cross-validate Gemini output against Pinecone catalog.
     *
     * Embeds Gemini's extracted data → searches Pinecone for similar boats →
     * detects anomalies where Gemini disagrees with 60%+ of similar boats.
     *
     * @return array  ['similar_boats' => [...], 'anomalies' => [...], 'anomaly_fields' => [...]]
     */
    private function runPineconeCrossValidation(array $formValues, array $fieldConfidence): array
    {
        $result = [
            'similar_boats'  => [],
            'anomalies'      => [],
            'anomaly_fields' => [],
        ];

        $openAiKey   = config('services.openai.key');
        $pineconeKey = config('services.pinecone.key');
        $pineconeHost = config('services.pinecone.host');

        if (!$openAiKey || !$pineconeKey || !$pineconeHost) {
            Log::info('[AI Pipeline] Pinecone cross-validation skipped: missing API keys');
            return $result;
        }

        try {
            // Build a text representation of what Gemini extracted
            $searchText = collect([
                $formValues['manufacturer'] ?? null,
                $formValues['model'] ?? null,
                $formValues['boat_type'] ?? null,
                $formValues['boat_name'] ?? null,
                $formValues['hull_construction'] ?? null,
                $formValues['fuel'] ?? null,
                $formValues['year'] ?? null,
            ])->filter()->implode(' ');

            if (strlen($searchText) < 3) {
                Log::info('[AI Pipeline] Not enough extracted data for Pinecone cross-validation');
                return $result;
            }

            // Step 1: Generate embedding
            $embedResponse = Http::withToken($openAiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => 'text-embedding-3-small',
                    'input' => $searchText,
                    'dimensions' => 1408,
                ]);

            if (!$embedResponse->successful()) {
                Log::warning('[AI Pipeline] Pinecone embedding failed: ' . $embedResponse->status());
                return $result;
            }

            $vector = $embedResponse->json('data.0.embedding');
            if (!$vector) {
                return $result;
            }

            // Step 2: Query Pinecone for top 5 similar boats
            $pineconeResponse = Http::withHeaders([
                'Api-Key'      => $pineconeKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$pineconeHost}/query", [
                'vector'          => $vector,
                'topK'            => 5,
                'includeMetadata' => true,
                'filter'          => [
                    'boat_ref' => ['$exists' => true]
                ]
            ]);

            if (!$pineconeResponse->successful()) {
                Log::warning('[AI Pipeline] Pinecone query failed: ' . $pineconeResponse->status() . ' - ' . $pineconeResponse->body());
                return $result;
            }

            $matches = $pineconeResponse->json('matches') ?? [];
            if (empty($matches)) {
                Log::info('[AI Pipeline] No Pinecone matches found');
                return $result;
            }

            $result['similar_boats'] = array_map(fn($m) => [
                'score'    => round(($m['score'] ?? 0) * 100),
                'metadata' => $m['metadata'] ?? [],
            ], $matches);

            // Step 3: Anomaly detection — compare key fields
            $fieldsToCheck = [
                'boat_type'         => 'type_name',      // map our field to Pinecone metadata
                'hull_construction' => 'hull_material',
                'fuel'              => 'fuel_type',
            ];

            foreach ($fieldsToCheck as $ourField => $pineconeField) {
                $ourValue = strtolower($formValues[$ourField] ?? '');
                if (empty($ourValue)) continue;

                // Count how many matches agree/disagree
                $agreeing = 0;
                $disagreeing = 0;
                $matchValues = [];

                foreach ($matches as $match) {
                    $meta = $match['metadata'] ?? [];
                    $theirValue = strtolower($meta[$pineconeField] ?? $meta[$ourField] ?? '');
                    if (empty($theirValue)) continue;

                    $matchValues[] = $theirValue;

                    // Fuzzy match — check if values overlap
                    if (str_contains($ourValue, $theirValue) || str_contains($theirValue, $ourValue)) {
                        $agreeing++;
                    } else {
                        $disagreeing++;
                    }
                }

                $total = $agreeing + $disagreeing;
                if ($total >= 3 && $disagreeing > ($total * 0.6)) {
                    // 60%+ of similar boats disagree with Gemini
                    $result['anomalies'][] = [
                        'field'           => $ourField,
                        'gemini_value'    => $formValues[$ourField],
                        'catalog_values'  => $matchValues,
                        'agree_count'     => $agreeing,
                        'disagree_count'  => $disagreeing,
                        'message'         => "Gemini says '{$formValues[$ourField]}' but {$disagreeing}/{$total} similar boats disagree",
                    ];
                    $result['anomaly_fields'][] = $ourField;
                }
            }

            Log::info('[AI Pipeline] Pinecone cross-validation complete', [
                'matches'   => count($matches),
                'anomalies' => count($result['anomalies']),
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('[AI Pipeline] Pinecone cross-validation exception: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Stage 3: ChatGPT Validation — GPT-4o acts as logic validator.
     *
     * Receives Gemini output, Pinecone matches, anomaly flags, and original images.
     * Its job is to VALIDATE (not extract): remove hallucinations, confirm/reject fields,
     * cross-check data consistency.
     *
     * @return array  ['confirmed_fields' => [...], 'removed_fields' => [...], 'adjusted_values' => [...], 'notes' => '...']
     */
    private function runChatGptValidation(
        array $formValues,
        array $fieldConfidence,
        array $pineconeResult,
        array $feedResult,
        array $databaseResult,
        Request $request
    ): array {
        $result = [
            'confirmed_fields' => [],
            'removed_fields'   => [],
            'adjusted_values'  => [],
            'suggested_additions' => [],
            'adjusted_confidence' => [],
            'notes'            => '',
        ];

        $openAiKey = config('services.openai.key');
        if (!$openAiKey) {
            Log::info('[AI Pipeline] ChatGPT validation skipped: no OpenAI key');
            $result['notes'] = 'chatgpt_validation_skipped_no_openai_key';
            return $result;
        }

        try {
            // Build the validation context
            $geminiOutput = json_encode(
                array_filter($formValues, fn($v) => $v !== null),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );

            $confidenceJson = json_encode($fieldConfidence, JSON_PRETTY_PRINT);
            $feedConsensusJson = json_encode($feedResult['consensus_values'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $anomaliesText = '';
            if (!empty($pineconeResult['anomalies'])) {
                $anomaliesText = "⚠️ ANOMALY FLAGS (Pinecone detected):\n";
                foreach ($pineconeResult['anomalies'] as $anomaly) {
                    $anomaliesText .= "- {$anomaly['field']}: Gemini says \"{$anomaly['gemini_value']}\" but {$anomaly['disagree_count']} of {$anomaly['agree_count']}+{$anomaly['disagree_count']} similar boats disagree. Similar values: " . implode(', ', array_unique($anomaly['catalog_values'])) . "\n";
                }
            }

            $similarBoatsText = '';
            if (!empty($pineconeResult['similar_boats'])) {
                $similarBoatsText = "SIMILAR BOATS FROM CATALOG:\n";
                foreach (array_slice($pineconeResult['similar_boats'], 0, 5) as $i => $boat) {
                    $meta = json_encode($boat['metadata'], JSON_PRETTY_PRINT);
                    $similarBoatsText .= "Match " . ($i + 1) . " (similarity: {$boat['score']}%):\n{$meta}\n\n";
                }
            }

            $feedMatchText = '';
            if (!empty($feedResult['top_matches'])) {
                $feedMatchText = "YACHTSHIFT FEED MATCHES:\n";
                foreach (array_slice($feedResult['top_matches'], 0, 1) as $idx => $match) {
                    $boat = json_encode($match['boat'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $feedMatchText .= "Pinecone Exact Match " . ($idx + 1) . " (score: {$match['score']}):\n{$boat}\n\n";
                }
            }

            $databaseMatchText = '';
            if (!empty($databaseResult['top_matches'])) {
                $databaseMatchText = "LOCAL DATABASE MATCHES (SOLD + AVAILABLE):\n";
                foreach (array_slice($databaseResult['top_matches'], 0, 5) as $idx => $match) {
                    $boat = json_encode($match['boat'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $databaseMatchText .= "Database Match " . ($idx + 1) . " (score: {$match['score']}):\n{$boat}\n\n";
                }
            }

            // Build messages for GPT-4o
            $messages = [];

            // System prompt: strict validator
            $messages[] = [
                'role'    => 'system',
                'content' => <<<VALIDATOR
You are a YACHT DATA VALIDATOR for NauticSecure. Your job is to VALIDATE the data and REMOVE CONTRADICTIONS, but PRESERVE INFERRED FACTORY SPECS.

VALIDATION RULES:
1. Prioritize verifiable evidence from images, readable text, and strong catalog matches.
2. Review the technical data in sections: Dimensions, Construction, Engines, Accommodation, Navigation, Safety, Electrical, Deck/Comfort, Rigging.
3. PRESERVE inferred factory specifications for well-known models (this is high-value data).
4. Remove inferred equipment/spec fields ONLY if there is clear contradicting evidence.
5. If Pinecone anomaly detected for a field (most similar boats disagree), REDUCE that field's confidence by 0.20.
6. Cross-check: if boat_type = "sailboat" but no mast/rigging visible in images/text → flag/adjust.
7. Optional equipment fields (life_jackets, bimini, anchor, fishfinder, bow_thruster, trailer, heating, toilet, fridge, etc.) must be yes/no/null.
8. If optional equipment evidence is missing, set value to null. Do NOT use the word "unknown" or "Unknown".
9. Ensure numeric values are realistic (e.g. LOA in meters, Beam < LOA).
10. All text values MUST be in Dutch (Nederlands). Translate English terms to Dutch (e.g. "White" -> "Wit", "Steel" -> "Staal", "Unknown" -> null).

You MUST respond in this JSON structure:
{
  "confirmed_fields": ["field1", "field2", ...],
  "removed_fields": [
    {"field": "microwave", "reason": "No microwave visible in any image, likely hallucinated"},
    {"field": "hull_colour", "reason": "Gemini said white but images show grey hull"}
  ],
  "adjusted_values": {
    "hull_colour": "grey",
    "boat_type": "motorboat"
  },
  "suggested_additions": {
    "manufacturer": "Bayliner",
    "model": "3055 Ciera"
  },
  "adjusted_confidence": {
    "field_name": 0.85
  },
  "notes": "Summary of validation findings"
}
VALIDATOR
            ];

            // User message with all the data
            $userContent = [];

            // Add images for GPT-4o Vision cross-check
            if ($request->hasFile('images')) {
                foreach (array_slice($request->file('images'), 0, 5) as $image) {
                    try {
                        $imageData = base64_encode(file_get_contents($image->getRealPath()));
                        $userContent[] = [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url'    => 'data:' . $image->getMimeType() . ';base64,' . $imageData,
                                'detail' => 'low', // low detail to save cost
                            ],
                        ];
                    } catch (\Exception $e) {
                        // skip
                    }
                }
            }

            // Add text context
            $userContent[] = [
                'type' => 'text',
                'text' => <<<CONTEXT
GEMINI EXTRACTED DATA:
{$geminiOutput}

GEMINI CONFIDENCE SCORES:
{$confidenceJson}

YACHTSHIFT FEED CONSENSUS:
{$feedConsensusJson}

{$anomaliesText}

{$similarBoatsText}

{$feedMatchText}

{$databaseMatchText}

USER HINT/DESCRIPTION:
"{$request->input('hint_text')}"

Based on the images above and the data provided, validate the Gemini extraction.
Remove any hallucinated fields. Confirm correct ones. Adjust incorrect values.
Respond in the JSON format specified.
CONTEXT,
            ];

            $messages[] = [
                'role'    => 'user',
                'content' => $userContent,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $openAiKey,
            ])->timeout(45)->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4o-mini',
                'messages'        => $messages,
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0.1, // very low for deterministic validation
                'max_tokens'      => 2000,
            ]);

            if (!$response->successful()) {
                Log::warning('[AI Pipeline] ChatGPT validation failed: ' . $response->status() . ' ' . $response->body());
                $result['notes'] = 'chatgpt_validation_failed';
                return $result;
            }

            $body = $response->json();
            $text = $body['choices'][0]['message']['content'] ?? null;

            if (!$text) {
                $result['notes'] = 'chatgpt_validation_failed';
                return $result;
            }

            $validated = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // OpenAI often wraps JSON in markdown blocks, try stripping them
                $cleaned = preg_replace('/```json\s*|\s*```/', '', $text);
                $validated = json_decode($cleaned, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('[AI Pipeline] Failed to parse ChatGPT validation JSON', ['raw_text' => $text]);
                    $result['notes'] = 'chatgpt_validation_failed';
                    return $result;
                }
            }

            Log::info('[AI Pipeline] ChatGPT validation complete', [
                'confirmed'   => count($validated['confirmed_fields'] ?? []),
                'removed'     => count($validated['removed_fields'] ?? []),
                'adjusted'    => count($validated['adjusted_values'] ?? []),
            ]);

            return [
                'confirmed_fields'    => $validated['confirmed_fields'] ?? [],
                'removed_fields'      => $validated['removed_fields'] ?? [],
                'adjusted_values'     => $validated['adjusted_values'] ?? [],
                'suggested_additions' => $validated['suggested_additions'] ?? [],
                'adjusted_confidence' => $validated['adjusted_confidence'] ?? [],
                'notes'               => $validated['notes'] ?? '',
            ];

        } catch (\Exception $e) {
            Log::error('[AI Pipeline] ChatGPT validation exception: ' . $e->getMessage());
            $result['notes'] = 'chatgpt_validation_failed';
            return $result;
        }
    }

    /**
     * Stage 4: Confidence-based merge.
     *
     * Applies ChatGPT validation results to the Gemini output:
     * - Removes hallucinated fields
     * - Applies value adjustments
     * - Enforces confidence thresholds (≥0.85 auto-fill, 0.40-0.84 review, <0.40 reject)
     *
     * @return array  ['form_values', 'field_confidence', 'removed_fields', 'needs_confirmation', 'validation_notes']
     */
    private function mergeWithConfidence(
        array $formValues,
        array $fieldConfidence,
        array $validationResult,
        array $pineconeResult
    ): array {
        $removedFields = [];
        $needsConfirmation = [];

        // Step 1: Remove fields flagged by ChatGPT
        foreach ($validationResult['removed_fields'] as $removal) {
            $field = is_array($removal) ? ($removal['field'] ?? '') : $removal;
            $reason = is_array($removal) ? ($removal['reason'] ?? 'Validation failed') : 'Validation failed';

            if (!empty($field) && isset($formValues[$field])) {
                $removedFields[] = [
                    'field'  => $field,
                    'value'  => $formValues[$field],
                    'reason' => $reason,
                    'source' => 'chatgpt_validation',
                ];
                $formValues[$field] = null;
                unset($fieldConfidence[$field]);
            }
        }

        // Step 2: Apply value adjustments from ChatGPT
        foreach ($validationResult['adjusted_values'] as $field => $newValue) {
            if (isset($formValues[$field]) || array_key_exists($field, $formValues)) {
                $formValues[$field] = $newValue;
            }
        }

        // Step 3: Apply safe additions for fields that are still empty
        foreach (($validationResult['suggested_additions'] ?? []) as $field => $newValue) {
            $current = $formValues[$field] ?? null;
            if ($newValue === null || $newValue === '') {
                continue;
            }
            if ($current === null || $current === '') {
                $formValues[$field] = $newValue;
                $fieldConfidence[$field] = max((float) ($fieldConfidence[$field] ?? 0.0), 0.82);
            }
        }

        // Step 4: Apply adjusted confidence from ChatGPT
        foreach ($validationResult['adjusted_confidence'] as $field => $newConf) {
            if (is_numeric($newConf)) {
                $fieldConfidence[$field] = (float) $newConf;
            }
        }

        // Step 5: Reduce confidence for Pinecone anomaly fields
        foreach ($pineconeResult['anomaly_fields'] as $field) {
            if (isset($fieldConfidence[$field])) {
                $fieldConfidence[$field] = max(0.0, $fieldConfidence[$field] - 0.20);
            }
        }

        // Step 6: Enforce confidence thresholds
        foreach ($formValues as $field => $value) {
            if ($value === null) continue;

            if (is_string($value) && strtolower(trim($value)) === 'unknown') {
                $formValues[$field] = null;
                $fieldConfidence[$field] = 0.50;
                continue;
            }

            $conf = $fieldConfidence[$field] ?? 0.5;

            if ($conf < 0.40) {
                // Below threshold — reject this field
                $removedFields[] = [
                    'field'  => $field,
                    'value'  => $value,
                    'reason' => "Confidence too low ({$conf})",
                    'source' => 'threshold_filter',
                ];
                $formValues[$field] = null;
                unset($fieldConfidence[$field]);
            } elseif ($conf < 0.85) {
                // Medium confidence — auto-fill but flag for review
                $needsConfirmation[] = $field;
            }
            // ≥ 0.85 → auto-fill, no review needed
        }

        return [
            'form_values'        => $formValues,
            'field_confidence'   => $fieldConfidence,
            'removed_fields'     => $removedFields,
            'needs_confirmation' => $needsConfirmation,
            'validation_notes'   => $validationResult['notes'] ?? '',
        ];
    }

    private function buildEmptyFormValues(): array
    {
        return array_fill_keys(self::STEP2_SCHEMA_FIELDS, null);
    }

    private function resolveStoredImagePath(\App\Models\YachtImage $yachtImage): ?string
    {
        $candidates = array_filter([
            $yachtImage->thumb_url,
            $yachtImage->optimized_master_url,
            $yachtImage->url,
            $yachtImage->original_kept_url,
            $yachtImage->original_temp_url,
        ]);

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $value = trim($candidate);
            if (preg_match('/^https?:\/\//i', $value) === 1) {
                $prefixes = [
                    rtrim(url('storage'), '/') . '/',
                    rtrim((string) config('app.url'), '/') . '/storage/',
                    '/storage/', // Relative fallback
                ];

                foreach ($prefixes as $prefix) {
                    if (str_contains($value, $prefix)) {
                        $parts = explode($prefix, $value);
                        $relative = ltrim(end($parts), '/');
                        $path = \Illuminate\Support\Facades\Storage::disk('public')->path($relative);
                        if (file_exists($path)) {
                            return $path;
                        }
                    }
                }
                continue;
            }

            $publicCandidate = ltrim((string) preg_replace('#^storage/#', '', $value), '/');
            $publicPath = \Illuminate\Support\Facades\Storage::disk('public')->path($publicCandidate);
            if (file_exists($publicPath)) {
                return $publicPath;
            }

            if (str_starts_with($value, '/')) {
                return $value;
            }
        }

        return null;
    }

    private function runDatabaseCatalogConsensus(array $formValues, ?string $hintText = null): array
    {
        $result = [
            'consensus_values' => [],
            'field_confidence' => [],
            'field_sources' => [],
            'top_matches' => [],
            'warnings' => [],
        ];

        try {
            $searchParts = array_filter([
                $formValues['manufacturer'] ?? null,
                $formValues['model'] ?? null,
                $formValues['boat_name'] ?? null,
                $formValues['boat_type'] ?? null,
                $hintText,
            ], fn($value) => is_string($value) ? trim($value) !== '' : $value !== null);

            $searchText = trim(implode(' ', $searchParts));
            if (mb_strlen($searchText) < 3) {
                return $result;
            }

            $terms = $this->tokenizeDatabaseSearchTerms($searchText);
            if (empty($terms)) {
                return $result;
            }

            $boats = Yacht::query()
                ->where(function ($query) use ($terms, $searchText, $formValues) {
                    $query->whereRaw('1 = 0');

                    if (!empty($formValues['manufacturer'])) {
                        $query->orWhere('manufacturer', 'like', '%' . $formValues['manufacturer'] . '%');
                    }
                    if (!empty($formValues['model'])) {
                        $query->orWhere('model', 'like', '%' . $formValues['model'] . '%');
                    }
                    if (!empty($formValues['boat_name'])) {
                        $query->orWhere('boat_name', 'like', '%' . $formValues['boat_name'] . '%');
                    }

                    $query->orWhereRaw(
                        "CONCAT(COALESCE(manufacturer, ''), ' ', COALESCE(model, ''), ' ', COALESCE(boat_name, '')) LIKE ?",
                        ['%' . $searchText . '%']
                    );

                    foreach ($terms as $term) {
                        $query->orWhere('manufacturer', 'like', '%' . $term . '%')
                            ->orWhere('model', 'like', '%' . $term . '%')
                            ->orWhere('boat_name', 'like', '%' . $term . '%')
                            ->orWhere('short_description_en', 'like', '%' . $term . '%')
                            ->orWhere('short_description_nl', 'like', '%' . $term . '%')
                            ->orWhere('owners_comment', 'like', '%' . $term . '%');
                    }
                })
                ->limit(30)
                ->get();

            if ($boats->isEmpty()) {
                return $result;
            }

            $scored = $boats
                ->map(function (Yacht $boat) use ($formValues, $terms, $searchText) {
                    $flat = $boat->toArray();

                    return [
                        'boat' => $boat,
                        'flat' => $flat,
                        'score' => $this->scoreDatabaseCandidate($flat, $formValues, $terms, $searchText),
                    ];
                })
                ->filter(fn(array $candidate) => $candidate['score'] > 0)
                ->sortByDesc('score')
                ->take(5)
                ->values();

            if ($scored->isEmpty()) {
                return $result;
            }

            $topScore = (float) ($scored->first()['score'] ?? 0.0);
            $result['top_matches'] = $scored->map(function (array $candidate) {
                /** @var Yacht $boat */
                $boat = $candidate['boat'];
                $flat = $candidate['flat'];

                return [
                    'score' => (int) round($candidate['score']),
                    'boat' => array_filter([
                        'id' => $boat->id,
                        'status' => $boat->status,
                        'manufacturer' => $boat->manufacturer,
                        'model' => $boat->model,
                        'boat_name' => $boat->boat_name,
                        'year' => $boat->year,
                        'loa' => $flat['loa'] ?? null,
                        'beam' => $flat['beam'] ?? null,
                        'draft' => $flat['draft'] ?? null,
                        'fuel' => $flat['fuel'] ?? null,
                        'engine_manufacturer' => $flat['engine_manufacturer'] ?? null,
                        'price' => $boat->price,
                        'source' => $boat->source ?? null,
                    ], fn($value) => $value !== null && $value !== ''),
                ];
            })->all();

            $fieldVotes = [];
            foreach ($scored as $candidate) {
                $weight = max(1.0, (float) $candidate['score']);
                $flat = $candidate['flat'];

                foreach (self::STEP2_SCHEMA_FIELDS as $field) {
                    $normalized = $this->normalizeDatabaseConsensusValue($field, $flat[$field] ?? null);
                    if ($normalized === null) {
                        continue;
                    }

                    $voteKey = is_bool($normalized) ? ($normalized ? 'true' : 'false') : (string) $normalized;
                    if (!isset($fieldVotes[$field][$voteKey])) {
                        $fieldVotes[$field][$voteKey] = [
                            'value' => $normalized,
                            'weight' => 0.0,
                            'count' => 0,
                        ];
                    }

                    $fieldVotes[$field][$voteKey]['weight'] += $weight;
                    $fieldVotes[$field][$voteKey]['count']++;
                }
            }

            foreach ($fieldVotes as $field => $votes) {
                uasort($votes, fn(array $left, array $right) => $right['weight'] <=> $left['weight']);
                $winner = reset($votes);
                if (!is_array($winner)) {
                    continue;
                }

                $totalWeight = array_sum(array_column($votes, 'weight'));
                $ratio = $totalWeight > 0 ? ($winner['weight'] / $totalWeight) : 0.0;
                $winnerCount = (int) ($winner['count'] ?? 0);

                if ($ratio < 0.55 && !($winnerCount >= 2 && $ratio >= 0.45) && !($topScore >= 92 && $winnerCount >= 1)) {
                    continue;
                }

                $result['consensus_values'][$field] = $winner['value'];
                $result['field_confidence'][$field] = round(min(0.93, 0.58 + ($ratio * 0.22) + (min($topScore, 100) / 100 * 0.12)), 2);
                $result['field_sources'][$field] = $winnerCount >= 2 ? 'database_catalog_consensus' : 'database_catalog_top_match';
            }
        } catch (\Throwable $e) {
            Log::warning('[AI Pipeline] Database catalog consensus failed', [
                'error' => $e->getMessage(),
            ]);
            $result['warnings'][] = 'Database catalog comparison failed: ' . $e->getMessage();
        }

        return $result;
    }

    private function tokenizeDatabaseSearchTerms(string $text): array
    {
        $terms = preg_split('/[^\pL\pN]+/u', mb_strtolower($text)) ?: [];
        $terms = array_values(array_unique(array_filter($terms, fn($term) => mb_strlen((string) $term) >= 3)));

        return array_slice($terms, 0, 12);
    }

    private function scoreDatabaseCandidate(array $flat, array $formValues, array $terms, string $searchText): float
    {
        $score = 0.0;
        $manufacturer = mb_strtolower(trim((string) ($flat['manufacturer'] ?? '')));
        $model = mb_strtolower(trim((string) ($flat['model'] ?? '')));
        $boatName = mb_strtolower(trim((string) ($flat['boat_name'] ?? '')));
        $boatType = mb_strtolower(trim((string) ($flat['boat_type'] ?? '')));
        $description = mb_strtolower(trim((string) (($flat['short_description_en'] ?? '') . ' ' . ($flat['short_description_nl'] ?? '') . ' ' . ($flat['owners_comment'] ?? ''))));
        $combined = trim(implode(' ', array_filter([$manufacturer, $model, $boatName, $boatType, $description])));
        $normalizedSearchText = mb_strtolower(trim($searchText));

        $searchManufacturer = mb_strtolower(trim((string) ($formValues['manufacturer'] ?? '')));
        $searchModel = mb_strtolower(trim((string) ($formValues['model'] ?? '')));
        $searchBoatName = mb_strtolower(trim((string) ($formValues['boat_name'] ?? '')));
        $searchBoatType = mb_strtolower(trim((string) ($formValues['boat_type'] ?? '')));

        if ($searchManufacturer !== '') {
            if ($manufacturer === $searchManufacturer) {
                $score += 35;
            } elseif (str_contains($manufacturer, $searchManufacturer) || str_contains($boatName, $searchManufacturer)) {
                $score += 24;
            }
        }

        if ($searchModel !== '') {
            if ($model === $searchModel) {
                $score += 38;
            } elseif (str_contains($model, $searchModel) || str_contains($boatName, $searchModel)) {
                $score += 26;
            }
        }

        if ($searchBoatName !== '') {
            if ($boatName === $searchBoatName) {
                $score += 40;
            } elseif (str_contains($boatName, $searchBoatName) || str_contains($searchBoatName, $boatName)) {
                $score += 24;
            }
        }

        if ($searchBoatType !== '' && $boatType !== '') {
            if ($boatType === $searchBoatType) {
                $score += 10;
            } elseif (str_contains($boatType, $searchBoatType) || str_contains($searchBoatType, $boatType)) {
                $score += 6;
            }
        }

        if ($normalizedSearchText !== '' && $combined !== '' && str_contains($combined, $normalizedSearchText)) {
            $score += 16;
        }

        foreach ($terms as $term) {
            if ($manufacturer !== '' && str_contains($manufacturer, $term)) {
                $score += 6;
            } elseif ($model !== '' && str_contains($model, $term)) {
                $score += 6;
            } elseif ($boatName !== '' && str_contains($boatName, $term)) {
                $score += 4;
            } elseif ($combined !== '' && str_contains($combined, $term)) {
                $score += 2;
            }
        }

        if (!empty($formValues['year']) && !empty($flat['year']) && (int) $formValues['year'] === (int) $flat['year']) {
            $score += 6;
        }

        if (!empty($formValues['fuel']) && !empty($flat['fuel']) && mb_strtolower((string) $formValues['fuel']) === mb_strtolower((string) $flat['fuel'])) {
            $score += 4;
        }

        return min(100.0, $score);
    }

    private function normalizeDatabaseConsensusValue(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || strtolower($trimmed) === 'null') {
                return null;
            }
            $value = $trimmed;
        }

        if (in_array($field, ['price', 'min_bid_amount'], true)) {
            if (!is_numeric($value)) {
                return null;
            }
            return (int) round((float) $value);
        }

        if (in_array($field, ['year', 'cabins', 'berths', 'engine_quantity', 'passenger_capacity'], true)) {
            if (!is_numeric($value)) {
                return null;
            }
            return (int) round((float) $value);
        }

        if (in_array($field, ['loa', 'lwl', 'beam', 'draft', 'air_draft', 'displacement', 'ballast', 'minimum_height', 'max_draft', 'min_draft'], true)) {
            if (!is_numeric($value)) {
                return null;
            }
            return round((float) $value, 2);
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * RAG-Based Description Generation — Step 3.
     *
     * Retrieval-augmented flow:
     *   1. Gather all Step 2 structured specs
     *   2. Search Pinecone for top 5 similar boats → collect their descriptions
     *   3. Query local DB for same brand/model boats with descriptions
     *   4. Build rich GPT-4o prompt with specs + example descriptions
     *   5. Generate NL/EN/DE marketplace-ready texts
     */
    public function generateDescription(Request $request): JsonResponse
    {
        $request->validate([
            'yacht_id' => 'required|integer|exists:yachts,id',
            'tone' => 'nullable|string',
            'min_words' => 'nullable|integer',
            'max_words' => 'nullable|integer',
        ]);

        $yacht = Yacht::findOrFail($request->input('yacht_id'));

        $openAiKey = config('services.openai.key');
        if (!$openAiKey) {
            return response()->json(['error' => 'OPENAI_API_KEY not configured'], 500);
        }

        $tone = $request->input('tone', 'professional');
        $minWords = $request->input('min_words', 200);
        $maxWords = $request->input('max_words', 500);

        // ── 1. GATHER STRUCTURED STEP 2 DATA ─────────────────────────────
        $yachtArray = $yacht->toArray();
        $skipFields = ['id', 'user_id', 'images', 'created_at', 'updated_at', 'deleted_at',
                       'location_lat', 'location_lng', 'pinecone_indexed_at', 'ai_extraction_id'];

        $allSpecs = collect($yachtArray)
            ->except($skipFields)
            ->filter(fn($val) => $val !== null && $val !== '' && $val !== 0 && $val !== '0')
            ->toArray();

        // Organize specs by category for better prompt context
        $specSections = $this->organizeSpecsForDescription($allSpecs);
        $specsText = '';
        foreach ($specSections as $section => $fields) {
            if (empty($fields)) continue;
            $specsText .= "\n### {$section}\n";
            foreach ($fields as $key => $val) {
                $label = str_replace('_', ' ', ucfirst($key));
                $specsText .= "- {$label}: {$val}\n";
            }
        }

        // ── 2. PINECONE SEARCH — Find similar boats with descriptions ────
        $exampleDescriptions = [];
        $similarBoatSummaries = [];

        try {
            $pineconeKey = config('services.pinecone.key');
            $pineconeHost = config('services.pinecone.host');

            if ($openAiKey && $pineconeKey && $pineconeHost) {
                $searchText = implode(' ', array_filter([
                    $yacht->manufacturer,
                    $yacht->model,
                    $yacht->boat_type,
                    $yacht->boat_category,
                    $yacht->year ? "year {$yacht->year}" : null,
                    $yacht->loa ? "{$yacht->loa}m" : null,
                    $yacht->fuel,
                ]));

                if (strlen(trim($searchText)) >= 3) {
                    // Embed the search text
                    $embedResponse = Http::withToken($openAiKey)
                        ->timeout(10)
                        ->post('https://api.openai.com/v1/embeddings', [
                            'model' => 'text-embedding-3-small',
                            'input' => $searchText,
                            'dimensions' => 1408,
                        ]);

                    if ($embedResponse->successful()) {
                        $vector = $embedResponse->json('data.0.embedding');
                        if ($vector) {
                            // Query Pinecone for top 5 similar boats
                            $pineconeResponse = Http::withHeaders([
                                'Api-Key' => $pineconeKey,
                                'Content-Type' => 'application/json',
                            ])->timeout(10)->post("{$pineconeHost}/query", [
                                'vector' => $vector,
                                'topK' => 5,
                                'includeMetadata' => true,
                            ]);

                            if ($pineconeResponse->successful()) {
                                $matches = $pineconeResponse->json('matches') ?? [];
                                foreach ($matches as $match) {
                                    $meta = $match['metadata'] ?? [];
                                    $score = round(($match['score'] ?? 0) * 100);
                                    $matchId = $meta['id'] ?? $match['id'] ?? null;

                                    // Build a summary of each similar boat
                                    $summary = implode(' ', array_filter([
                                        $meta['manufacturer'] ?? null,
                                        $meta['model'] ?? null,
                                        isset($meta['year']) ? "({$meta['year']})" : null,
                                        isset($meta['loa']) ? "– {$meta['loa']}m" : null,
                                        isset($meta['boat_type']) ? "– {$meta['boat_type']}" : null,
                                    ]));
                                    if ($summary) {
                                        $similarBoatSummaries[] = "{$summary} (match: {$score}%)";
                                    }

                                    // Try to load the actual description from local DB
                                    if ($matchId) {
                                        $matchYacht = Yacht::find($matchId);
                                        if ($matchYacht) {
                                            $desc = $matchYacht->short_description_nl ?: $matchYacht->short_description_en;
                                            if ($desc && strlen($desc) > 80) {
                                                $brand = $matchYacht->manufacturer ?: '';
                                                $model = $matchYacht->model ?: '';
                                                $exampleDescriptions[] = [
                                                    'boat' => trim("{$brand} {$model} ({$matchYacht->year})"),
                                                    'text' => mb_substr($desc, 0, 1500), // Cap to save tokens
                                                    'lang' => $matchYacht->short_description_nl ? 'nl' : 'en',
                                                ];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[AI Description RAG] Pinecone search failed: ' . $e->getMessage());
        }

        // ── 3. LOCAL DB SEARCH — Same brand/model boats with descriptions ─
        try {
            $dbQuery = Yacht::query()
                ->where('id', '!=', $yacht->id)
                ->where(function($q) {
                    $q->whereNotNull('short_description_nl')
                      ->where('short_description_nl', '!=', '')
                      ->where('short_description_nl', '!=', ' ');
                });

            // First try exact brand+model match
            if ($yacht->manufacturer && $yacht->model) {
                $brandModelMatches = (clone $dbQuery)
                    ->where('manufacturer', 'LIKE', $yacht->manufacturer)
                    ->where('model', 'LIKE', "%{$yacht->model}%")
                    ->limit(3)
                    ->get();

                foreach ($brandModelMatches as $bm) {
                    $desc = $bm->short_description_nl ?: $bm->short_description_en;
                    if ($desc && strlen($desc) > 80 && count($exampleDescriptions) < 5) {
                        $exampleDescriptions[] = [
                            'boat' => trim("{$bm->manufacturer} {$bm->model} ({$bm->year})"),
                            'text' => mb_substr($desc, 0, 1500),
                            'lang' => $bm->short_description_nl ? 'nl' : 'en',
                        ];
                    }
                }
            }

            // Fallback: same category/type boats
            if (count($exampleDescriptions) < 3 && $yacht->boat_type) {
                $categoryMatches = (clone $dbQuery)
                    ->where('boat_type', $yacht->boat_type)
                    ->when($yacht->manufacturer, fn($q) => $q->where('manufacturer', $yacht->manufacturer))
                    ->inRandomOrder()
                    ->limit(3)
                    ->get();

                foreach ($categoryMatches as $cm) {
                    $desc = $cm->short_description_nl ?: $cm->short_description_en;
                    if ($desc && strlen($desc) > 80 && count($exampleDescriptions) < 5) {
                        $exampleDescriptions[] = [
                            'boat' => trim("{$cm->manufacturer} {$cm->model} ({$cm->year})"),
                            'text' => mb_substr($desc, 0, 1500),
                            'lang' => $cm->short_description_nl ? 'nl' : 'en',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[AI Description RAG] DB search failed: ' . $e->getMessage());
        }

        // Deduplicate example descriptions by boat name
        $seen = [];
        $exampleDescriptions = array_values(array_filter($exampleDescriptions, function($ex) use (&$seen) {
            if (in_array($ex['boat'], $seen)) return false;
            $seen[] = $ex['boat'];
            return true;
        }));

        // ── 4. BUILD RICH GPT-4o PROMPT ──────────────────────────────────
        $boatTitle = implode(' ', array_filter([$yacht->manufacturer, $yacht->model, $yacht->year ? "({$yacht->year})" : '']));

        $examplesBlock = '';
        if (!empty($exampleDescriptions)) {
            $examplesBlock = "\n\n## EXAMPLE DESCRIPTIONS FROM SIMILAR BOATS (use as style/quality reference, do NOT copy):\n";
            foreach (array_slice($exampleDescriptions, 0, 3) as $i => $ex) {
                $n = $i + 1;
                $examplesBlock .= "\n### Example {$n}: {$ex['boat']} ({$ex['lang']})\n{$ex['text']}\n";
            }
        }

        $similarBlock = '';
        if (!empty($similarBoatSummaries)) {
            $similarBlock = "\n\n## SIMILAR BOATS IN OUR DATABASE:\n" . implode("\n", array_map(fn($s) => "- {$s}", $similarBoatSummaries));
        }

        $systemPrompt = <<<PROMPT
You are a senior yacht broker copywriter for Schepenkring, one of the largest yacht brokerages in the Netherlands and Europe.
You write compelling, trustworthy, and detailed boat listing descriptions that sell boats.

Your writing style:
- Professional yet warm, like an experienced broker personally recommending the boat
- Structured: start with a captivating opening, then walk through key features logically
- Highlight unique selling points, luxury features, and practical benefits
- Mention specific technical details (engine, dimensions, equipment) naturally in the text
- End with a call to action or invitation to schedule a viewing

CRITICAL RULES:
1. Tone: {$tone}
2. Word count: Each language version MUST be between {$minWords} and {$maxWords} words. This is a HARD requirement.
3. Languages: Generate in Dutch (nl), English (en), and German (de). Each should feel native, NOT a translation.
4. ONLY mention specifications, equipment, and features that are provided in the data below. NEVER invent or hallucinate specs.
5. If a spec is missing, simply don't mention it. Do not write "unknown" or "not specified".
6. Use the example descriptions below as STYLE and QUALITY reference. Match their professional level. Do NOT copy their text.
7. Return ONLY a valid JSON object with keys "nl", "en", "de". No markdown, no explanation.

PROMPT;

        $userPrompt = <<<USER
# BOAT: {$boatTitle}

## ALL SPECIFICATIONS (Step 2 Data):
{$specsText}
{$similarBlock}
{$examplesBlock}

Generate the listing descriptions now. Remember: {$minWords}–{$maxWords} words per language, based ONLY on the specs above.
USER;

        Log::info('[AI Description RAG] Generating for ' . $boatTitle, [
            'specs_count' => count($allSpecs),
            'pinecone_matches' => count($similarBoatSummaries),
            'example_descriptions' => count($exampleDescriptions),
        ]);

        // ── 5. CALL GPT-4o ──────────────────────────────────────────────
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $openAiKey,
            ])->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
            ]);

            if (!$response->successful()) {
                Log::error('[AI Description RAG] API error: ' . $response->body());
                return response()->json(['error' => 'OpenAI API failed'], 500);
            }

            $body = $response->json();
            $text = $body['choices'][0]['message']['content'] ?? null;

            if (!$text) {
                return response()->json(['error' => 'Empty response from OpenAI'], 500);
            }

            $extracted = json_decode($text, true);

            if (!$extracted || (!isset($extracted['nl']) && !isset($extracted['en']))) {
                Log::error('[AI Description RAG] Invalid JSON response', ['raw' => $text]);
                return response()->json(['error' => 'Invalid AI response format'], 500);
            }

            Log::info('[AI Description RAG] Generated successfully for ' . $boatTitle, [
                'nl_words' => str_word_count($extracted['nl'] ?? ''),
                'en_words' => str_word_count($extracted['en'] ?? ''),
                'de_words' => str_word_count($extracted['de'] ?? ''),
            ]);

            return response()->json([
                'success' => true,
                'descriptions' => [
                    'en' => $extracted['en'] ?? null,
                    'nl' => $extracted['nl'] ?? null,
                    'de' => $extracted['de'] ?? null,
                    'fr' => $extracted['fr'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('[AI Description RAG] Exception: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Organize yacht specs into logical sections for the description prompt.
     */
    private function organizeSpecsForDescription(array $specs): array
    {
        $sections = [
            'General' => ['boat_name', 'manufacturer', 'model', 'boat_type', 'boat_category',
                          'year', 'new_or_used', 'status', 'price', 'vessel_lying', 'location_city',
                          'ce_category', 'flag', 'registration'],
            'Dimensions' => ['loa', 'lwl', 'beam', 'draft', 'air_draft', 'displacement', 'ballast',
                            'max_draft', 'min_draft', 'variable_depth', 'minimum_height'],
            'Engine & Propulsion' => ['engine_manufacturer', 'engine_model', 'engine_quantity', 'horse_power',
                                     'fuel', 'fuel_capacity', 'drive_type', 'propulsion', 'engine_hours',
                                     'max_speed', 'cruising_speed', 'range', 'bow_thruster', 'stern_thruster'],
            'Accommodation' => ['cabins', 'berths', 'heads', 'toilet', 'shower', 'passenger_capacity',
                               'heating', 'air_conditioning', 'hot_water'],
            'Navigation & Electronics' => ['autopilot', 'gps', 'plotter', 'radar', 'vhf', 'ais',
                                          'compass', 'depth_sounder', 'log_speedometer', 'wind_instrument'],
            'Deck & Comfort' => ['bimini', 'sprayhood', 'cockpit_cover', 'swimming_platform', 'teak_deck',
                                'anchor', 'windlass', 'bathing_ladder', 'dinghy', 'davits', 'gangway'],
            'Kitchen & Comfort' => ['oven', 'microwave', 'fridge', 'freezer', 'cooker', 'television',
                                   'radio_cd_player', 'dvd_player', 'water_tank', 'water_maker'],
            'Safety' => ['life_raft', 'fire_extinguisher', 'epirb', 'life_jackets', 'safety_harness',
                        'bilge_pump', 'gas_detector', 'fire_blanket'],
            'Sails & Rigging' => ['mainsail', 'genoa', 'jib', 'spinnaker', 'gennaker', 'furling_mast',
                                 'furling_genoa', 'lazy_jacks', 'battened_mainsail', 'sail_area'],
            'Construction' => ['hull_type', 'hull_construction', 'hull_colour', 'hull_number',
                              'super_structure_colour', 'super_structure_construction',
                              'designer', 'builder', 'where'],
            'Electrical' => ['shore_power', 'battery_charger', 'inverter', 'generator',
                            'solar_panels', 'wind_generator', 'battery_capacity'],
            'Description & Remarks' => ['short_description_nl', 'short_description_en', 'short_description_de',
                                       'owners_comment', 'broker_remarks'],
        ];

        $organized = [];
        $assigned = [];

        foreach ($sections as $section => $keys) {
            $sectionData = [];
            foreach ($keys as $key) {
                if (isset($specs[$key])) {
                    $sectionData[$key] = $specs[$key];
                    $assigned[] = $key;
                }
            }
            if (!empty($sectionData)) {
                $organized[$section] = $sectionData;
            }
        }

        // Add any remaining fields not in a section
        $remaining = array_diff_key($specs, array_flip($assigned));
        if (!empty($remaining)) {
            $organized['Other Details'] = $remaining;
        }

        return $organized;
    }

    /**
     * POST /api/ai/suggestions
     *
     * Get boat specification suggestions based on similar sold boats via Pinecone.
     */
    public function getSuggestions(Request $request, \App\Services\PineconeMatcherService $pineconeMatcher): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:3',
        ]);

        $query = $request->input('query');
        
        $partialValues = [
            'manufacturer' => $query,
            'model' => $query,
        ];

        $result = $pineconeMatcher->matchAndBuildConsensus($partialValues, $query);
        $result = $this->prepareAssistantSuggestions($result);
        $result = $this->mergeLocalArchiveSuggestions($result, $query);

        return response()->json($result);
    }

    /**
     * Suggestions endpoint is assistive (human-in-the-loop), so if strict consensus is empty
     * we derive conservative fallback values from top matches.
     */
    private function prepareAssistantSuggestions(array $result): array
    {
        $matches = $result['top_matches'] ?? [];
        if (!is_array($matches)) {
            $matches = [];
        }

        $preparedMatches = [];
        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $score = (int) round((float) ($match['score'] ?? 0));
            $boatMeta = is_array($match['boat'] ?? null) ? $match['boat'] : [];
            $preparedMatches[] = [
                'score' => $score,
                'boat' => $this->extractSuggestionFieldsFromMetadata($boatMeta),
            ];
        }

        $result['top_matches'] = $preparedMatches;
        $result['consensus_values'] = is_array($result['consensus_values'] ?? null)
            ? $this->filterSuggestionFields($result['consensus_values'])
            : [];
        $result['field_confidence'] = is_array($result['field_confidence'] ?? null)
            ? $this->filterSuggestionFields($result['field_confidence'])
            : [];
        $result['field_sources'] = is_array($result['field_sources'] ?? null)
            ? $this->filterSuggestionFields($result['field_sources'])
            : [];

        // Strict mode may return no consensus; for UX suggestions we still provide conservative hints.
        if (!empty($result['consensus_values'])) {
            return $result;
        }

        $fallback = [];
        foreach ($preparedMatches as $match) {
            $boat = is_array($match['boat'] ?? null) ? $match['boat'] : [];
            foreach (self::SUGGESTION_TARGET_FIELDS as $field) {
                $value = $boat[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $fallback[$field] ??= $value;
            }
        }

        if (empty($fallback)) {
            return $result;
        }

        foreach ($fallback as $field => $value) {
            $result['consensus_values'][$field] = $value;
            $result['field_confidence'][$field] = 0.58;
            $result['field_sources'][$field] = 'pinecone_top_match';
        }

        $result['warnings'] = array_values(array_filter(array_merge(
            is_array($result['warnings'] ?? null) ? $result['warnings'] : [],
            ['No strict consensus found; showing conservative top-match suggestions.']
        )));

        return $result;
    }

    private function mergeLocalArchiveSuggestions(array $result, string $query): array
    {
        $local = $this->buildLocalArchiveSuggestions($query);
        if (empty($local['consensus_values'])) {
            return $result;
        }

        $result['consensus_values'] = is_array($result['consensus_values'] ?? null) ? $result['consensus_values'] : [];
        $result['field_confidence'] = is_array($result['field_confidence'] ?? null) ? $result['field_confidence'] : [];
        $result['field_sources'] = is_array($result['field_sources'] ?? null) ? $result['field_sources'] : [];
        $result['top_matches'] = is_array($result['top_matches'] ?? null) ? $result['top_matches'] : [];
        $result['warnings'] = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];

        foreach ($local['consensus_values'] as $field => $value) {
            if (($result['consensus_values'][$field] ?? null) !== null && ($result['consensus_values'][$field] ?? null) !== '') {
                continue;
            }
            $result['consensus_values'][$field] = $value;
            $result['field_confidence'][$field] = $local['field_confidence'][$field] ?? 0.64;
            $result['field_sources'][$field] = $local['field_sources'][$field] ?? 'local_archive_consensus';
        }

        if (empty($result['top_matches'])) {
            $result['top_matches'] = $local['top_matches'];
        } else {
            $result['top_matches'] = array_values(array_slice(
                array_merge($result['top_matches'], $local['top_matches']),
                0,
                5
            ));
        }

        if (!empty($local['consensus_values'])) {
            $result['warnings'][] = 'Suggestions include local archive fallback because Pinecone consensus was sparse.';
        }
        $result['warnings'] = array_values(array_unique(array_filter($result['warnings'])));

        return $result;
    }

    private function buildLocalArchiveSuggestions(string $query): array
    {
        $result = [
            'consensus_values' => [],
            'field_confidence' => [],
            'field_sources' => [],
            'top_matches' => [],
        ];

        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 3) {
            return $result;
        }

        try {
            $terms = array_values(array_filter(preg_split('/\s+/', $query) ?: [], function ($term) {
                return mb_strlen((string) $term) >= 3;
            }));

            $boats = Yacht::query()
                ->where(function ($q) use ($query, $terms) {
                    $q->where('manufacturer', 'like', '%' . $query . '%')
                        ->orWhere('model', 'like', '%' . $query . '%')
                        ->orWhereRaw("CONCAT(COALESCE(manufacturer, ''), ' ', COALESCE(model, '')) LIKE ?", ['%' . $query . '%']);

                    foreach ($terms as $term) {
                        $q->orWhere('manufacturer', 'like', '%' . $term . '%')
                            ->orWhere('model', 'like', '%' . $term . '%');
                    }
                })
                ->orderByDesc('year')
                ->limit(12)
                ->get();

            if ($boats->isEmpty()) {
                return $result;
            }

            $numericFields = ['year', 'loa', 'beam', 'draft', 'engine_quantity', 'horse_power', 'price'];
            $textFields = ['engine_manufacturer', 'fuel'];
            $values = [];

            foreach (array_merge($numericFields, $textFields) as $field) {
                $values[$field] = [];
            }

            foreach ($boats as $boat) {
                $flat = $boat->toArray();
                foreach ($numericFields as $field) {
                    $num = $this->extractSuggestionNumeric($flat[$field] ?? null);
                    if ($num !== null) {
                        $values[$field][] = $num;
                    }
                }
                foreach ($textFields as $field) {
                    $txt = $this->normalizeSuggestionValue($field, $flat[$field] ?? null);
                    if ($txt !== null && $txt !== '') {
                        $values[$field][] = (string) $txt;
                    }
                }
            }

            foreach ($numericFields as $field) {
                if (empty($values[$field])) {
                    continue;
                }
                sort($values[$field]);
                $count = count($values[$field]);
                $mid = intdiv($count, 2);
                $median = $count % 2 === 0
                    ? (($values[$field][$mid - 1] + $values[$field][$mid]) / 2)
                    : $values[$field][$mid];

                if (in_array($field, ['year', 'engine_quantity', 'horse_power'], true)) {
                    $median = (int) round($median);
                } else {
                    $median = round((float) $median, 2);
                }

                $result['consensus_values'][$field] = $median;
                $result['field_confidence'][$field] = round(min(0.86, 0.56 + ($count / max(1, $boats->count())) * 0.30), 2);
                $result['field_sources'][$field] = 'local_archive_consensus';
            }

            foreach ($textFields as $field) {
                if (empty($values[$field])) {
                    continue;
                }
                $counts = array_count_values($values[$field]);
                arsort($counts);
                $winner = array_key_first($counts);
                if ($winner === null || $winner === '') {
                    continue;
                }
                $winnerCount = (int) ($counts[$winner] ?? 0);
                $ratio = $winnerCount / max(1, count($values[$field]));
                if ($winnerCount < 2 && $ratio < 0.45) {
                    continue;
                }

                $result['consensus_values'][$field] = $winner;
                $result['field_confidence'][$field] = round(min(0.85, 0.52 + $ratio * 0.33), 2);
                $result['field_sources'][$field] = 'local_archive_consensus';
            }

            $result['top_matches'] = $boats->take(3)->map(function (Yacht $boat) {
                $flat = $boat->toArray();
                return [
                    'score' => 50,
                    'boat' => array_filter([
                        'id' => $boat->id,
                        'manufacturer' => $boat->manufacturer,
                        'model' => $boat->model,
                        'year' => $boat->year,
                        'loa' => $flat['loa'] ?? null,
                        'beam' => $flat['beam'] ?? null,
                        'draft' => $flat['draft'] ?? null,
                        'price' => $boat->price,
                        'source_feed_url' => 'local_archive',
                    ], fn($val) => $val !== null && $val !== ''),
                ];
            })->values()->toArray();
        } catch (\Throwable $e) {
            Log::warning('[AI Suggestions] Local archive fallback failed: ' . $e->getMessage());
        }

        return $result;
    }

    private function filterSuggestionFields(array $values): array
    {
        $filtered = [];
        foreach (self::SUGGESTION_TARGET_FIELDS as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            $value = $values[$field];
            if ($value === null || $value === '') {
                continue;
            }
            $filtered[$field] = $value;
        }
        return $filtered;
    }

    private function extractSuggestionFieldsFromMetadata(array $metadata): array
    {
        $source = $metadata;
        $embedded = $this->decodeEmbeddedSuggestionPayload($metadata);
        if (is_array($embedded)) {
            $source = array_merge($source, ['_embedded_payload' => $embedded]);
        }

        $flat = $this->flattenSuggestionSource($source);

        $aliases = [
            'manufacturer' => ['manufacturer', 'make', 'merk'],
            'model' => ['model', 'model_name'],
            'boat_type' => ['boat_type', 'vessel_type'],
            'year' => ['year', 'build_year', 'bouwjaar'],
            'loa' => ['loa', 'length_overall', 'length_m', 'length'],
            'beam' => ['beam', 'beam_m', 'width', 'breedte'],
            'draft' => ['draft', 'draught', 'draft_m', 'diepgang'],
            'engine_manufacturer' => ['engine_manufacturer', 'engine_brand', 'motor_merk'],
            'fuel' => ['fuel', 'fuel_type', 'brandstof'],
            'engine_quantity' => ['engine_quantity', 'number_of_engines', 'engines'],
            'horse_power' => ['horse_power', 'hp', 'vermogen'],
            'price' => ['price', 'asking_price', 'vraagprijs', 'sale_price'],
        ];

        $extracted = [];
        foreach ($aliases as $field => $keys) {
            foreach ($keys as $key) {
                $normalizedKey = $this->normalizeSuggestionKey($key);
                if (!array_key_exists($normalizedKey, $flat)) {
                    continue;
                }
                $normalizedValue = $this->normalizeSuggestionValue($field, $flat[$normalizedKey]);
                if ($normalizedValue === null || $normalizedValue === '') {
                    continue;
                }
                $extracted[$field] = $normalizedValue;
                break;
            }
        }

        // Keep lightweight identity/debug fields only (never include full payload blobs).
        foreach (['boat_ref', 'source_feed_url', 'synced_at_utc'] as $infoField) {
            if (isset($metadata[$infoField]) && is_scalar($metadata[$infoField])) {
                $extracted[$infoField] = (string) $metadata[$infoField];
            }
        }

        return $extracted;
    }

    private function decodeEmbeddedSuggestionPayload(array $metadata): ?array
    {
        $encoded = $metadata['full_payload_gzip_b64'] ?? null;
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            return null;
        }

        $decodedCandidates = [
            @gzdecode($binary),
            @gzuncompress($binary),
            @gzinflate($binary),
            @gzinflate(substr($binary, 10)),
        ];

        $decoded = null;
        foreach ($decodedCandidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                $decoded = $candidate;
                break;
            }
        }

        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $json = json_decode($decoded, true);
        if (is_array($json)) {
            return $json;
        }

        if (is_string($json)) {
            $nested = json_decode($json, true);
            if (is_array($nested)) {
                return $nested;
            }
        }

        $trimmed = ltrim($decoded);
        if ($trimmed !== '' && str_starts_with($trimmed, '<')) {
            try {
                $xml = @simplexml_load_string($decoded, 'SimpleXMLElement', LIBXML_NOCDATA);
                if ($xml !== false) {
                    $xmlArr = json_decode(json_encode($xml), true);
                    if (is_array($xmlArr)) {
                        return $xmlArr;
                    }
                }
            } catch (\Throwable) {
                // Ignore XML parse errors and keep strict null fallback.
            }
        }

        return null;
    }

    private function flattenSuggestionSource(array $source, string $prefix = ''): array
    {
        $flat = [];
        foreach ($source as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $normalizedKey = $this->normalizeSuggestionKey($key);
            if ($normalizedKey === '') {
                continue;
            }

            $compositeKey = $prefix === '' ? $normalizedKey : ($prefix . '_' . $normalizedKey);

            if (is_array($value)) {
                $flat += $this->flattenSuggestionSource($value, $compositeKey);
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $flat[$normalizedKey] ??= $value;
            $flat[$compositeKey] = $value;
        }

        return $flat;
    }

    private function normalizeSuggestionKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
        return trim($key, '_');
    }

    private function normalizeSuggestionValue(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, ['year', 'engine_quantity', 'horse_power', 'price', 'loa', 'beam', 'draft'], true)) {
            $numeric = $this->extractSuggestionNumeric($value);
            if ($numeric === null) {
                return null;
            }

            if ($field === 'year' || $field === 'engine_quantity' || $field === 'horse_power') {
                return (int) round($numeric);
            }

            return round($numeric, 2);
        }

        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (!$this->isValidSuggestionTextValue($field, $text)) {
            return null;
        }

        return $text;
    }

    private function extractSuggestionNumeric(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $text = trim($value);
        if ($text === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.\-]+/', '', $text) ?? '';
        if ($normalized === '') {
            return null;
        }

        // If both separators exist, assume dots are thousand separators and comma is decimal.
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function isValidSuggestionTextValue(string $field, string $value): bool
    {
        $normalized = strtolower(trim($value));

        $blocked = [
            'unknown',
            'n/a',
            'na',
            'none',
            'elsewhere',
            'office',
            'other',
        ];

        if (in_array($normalized, $blocked, true)) {
            return false;
        }

        if ($field === 'boat_type') {
            $keywords = ['boat', 'yacht', 'sail', 'motor', 'catamaran', 'rib', 'sloep', 'jacht', 'zeil', 'trawler'];
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private function prepareGeminiVisionPayload(Request $request, string $apiKey): ?array
    {
        $visionImages = [];
        $maxVisionImages = 5; // Reduced for speed in parallel track
        
        // 1. Get images from DB if yacht_id provided
        if ($request->has('yacht_id')) {
            $yacht = \App\Models\Yacht::find($request->yacht_id);
            if ($yacht) {
                $images = $yacht->images()->orderBy('sort_order')->limit($maxVisionImages)->get();
                foreach ($images as $img) {
                    $path = $this->resolveStoredImagePath($img);
                    if ($path && file_exists($path)) {
                        $visionImages[] = [
                            'mime_type' => mime_content_type($path) ?: 'image/jpeg',
                            'data' => base64_encode(file_get_contents($path))
                        ];
                    }
                }
            }
        }

        // 2. Fallback to uploaded images if needed
        if (count($visionImages) < $maxVisionImages && $request->hasFile('images')) {
            foreach (array_slice($request->file('images'), 0, $maxVisionImages - count($visionImages)) as $image) {
                $visionImages[] = [
                    'mime_type' => $image->getMimeType(),
                    'data' => base64_encode(file_get_contents($image->getRealPath()))
                ];
            }
        }

        if (empty($visionImages)) return null;

        $model = 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        $parts = [];
        $parts[] = ['text' => $this->getGeminiSchema() . "\n\nUSER HINT: " . $request->input('hint_text')];
        foreach ($visionImages as $img) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $img['mime_type'],
                    'data' => $img['data']
                ]
            ];
        }

        return [
            'url' => $url,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['contents' => [['parts' => $parts]]],
            'timeout' => 50,
            'image_count' => count($visionImages)
        ];
    }

    private function getOpenAiEnrichmentPrompt(string $hintText): string
    {
        return "You are a marine data expert. Extract boat specifications from the following text into JSON format. " .
               "FIELDS TO EXTRACT: manufacturer, model, boat_type, year, price, loa, beam, draft, cabins, berths, fuel, engine_manufacturer, hull_construction.\n\nTEXT: " . $hintText;
    }

    private function mergeConsensusValue(string $field, $value, float $confidence, string $source, array &$formValues, array &$fieldConfidence, array &$fieldSources, array &$needsConfirmation): void
    {
        if ($value === null || $value === '') return;

        $existingValue = $formValues[$field] ?? null;
        $existingConf = (float) ($fieldConfidence[$field] ?? 0.0);

        if ($existingValue === null || $existingValue === '') {
            $formValues[$field] = $value;
            $fieldConfidence[$field] = $confidence;
            $fieldSources[$field] = $source;
            return;
        }

        if ((string) $existingValue !== (string) $value) {
            if ($confidence > $existingConf + 0.10) {
                $formValues[$field] = $value;
                $fieldConfidence[$field] = $confidence;
                $fieldSources[$field] = $source . '_override';
                $needsConfirmation[] = $field;
            }
        }
    }

    private function cleanGeminiJson(string $text): string
    {
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        $text = trim($text);
        return $text;
    }
}
