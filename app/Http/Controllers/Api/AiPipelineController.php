<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Yacht;
use App\Services\PineconeMatcherService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiPipelineController extends Controller
{
    /**
     * Required fields — if any of these are null after Stage 1, enrichment triggers.
     */
    private const REQUIRED_FIELDS = ['boat_name', 'year', 'loa', 'hull_type'];

    /**
     * Confidence threshold — below this, enrichment triggers.
     */
    private const CONFIDENCE_THRESHOLD = 0.70;

    /**
     * POST /api/ai/pipeline-extract
     *
     * Multi-stage AI Fill Pipeline:
     *   Stage 1: Gemini Vision extraction (images + hint → structured JSON)
     *   Gate:    Check confidence + required fields
     *   Stage 2: Gemini enrichment with DB fleet data as RAG context (only if gate fails)
     *   Return:  Unified step2_form_values + meta
     */
    public function extractAndEnrich(Request $request, \App\Services\PineconeMatcherService $pineconeMatcher): JsonResponse
    {
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
        $formValues = [];
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

        // ─── STAGE 1: Gemini Vision Extract ───────────────────────────
        $stage1Result = $this->runGeminiVisionExtract($request, $apiKey);
        $visionImagesUsed = (int) ($stage1Result['image_count'] ?? 0);

        if (isset($stage1Result['error'])) {
            Log::warning('[AI Pipeline] Stage 1 (Gemini) failed, proceeding to fallbacks. Error: ' . $stage1Result['error']);
            $warnings[] = 'Primary vision extraction failed: ' . $stage1Result['error'];
            
            // Initialize null form values for all schema keys so enrichment can try to fill them
            $schemaKeys = [
                'boat_name', 'manufacturer', 'model', 'boat_type', 'boat_category', 'new_or_used',
                'year', 'price', 'loa', 'lwl', 'beam', 'draft', 'air_draft', 'displacement',
                'ballast', 'hull_colour', 'hull_construction', 'hull_type', 'hull_number',
                'designer', 'builder', 'where', 'deck_colour', 'deck_construction',
                'super_structure_colour', 'super_structure_construction', 'cockpit_type',
                'control_type', 'flybridge', 'engine_manufacturer', 'engine_model', 'engine_type',
                'horse_power', 'hours', 'fuel', 'engine_quantity', 'engine_year', 'cruising_speed',
                'max_speed', 'drive_type', 'propulsion', 'cabins', 'berths', 'toilet', 'shower',
                'bath', 'heating', 'air_conditioning', 'ce_category', 'passenger_capacity',
                'compass', 'gps', 'radar', 'autopilot', 'vhf', 'plotter', 'depth_instrument',
                'wind_instrument', 'speed_instrument', 'navigation_lights', 'life_raft', 'epirb',
                'fire_extinguisher', 'bilge_pump', 'mob_system', 'life_jackets', 'radar_reflector',
                'flares', 'battery', 'battery_charger', 'generator', 'inverter', 'shorepower',
                'solar_panel', 'wind_generator', 'voltage', 'anchor', 'anchor_winch', 'bimini',
                'spray_hood', 'swimming_platform', 'swimming_ladder', 'teak_deck', 'cockpit_table',
                'dinghy', 'covers', 'spinnaker', 'fenders', 'television', 'cd_player', 'dvd_player',
                'satellite_reception', 'oven', 'microwave', 'fridge', 'freezer', 'cooker',
                'owners_comment', 'reg_details', 'known_defects', 'last_serviced', 
                'short_description_en', 'short_description_nl', 'short_description_de'
            ];
            
            foreach ($schemaKeys as $key) {
                // Only set to null if not already filled by Stage 0
                if (!isset($formValues[$key])) {
                    $formValues[$key] = null;
                }
            }
            
            // Keep hint_text as short_description_en
            if (!isset($formValues['short_description_en']) || $formValues['short_description_en'] === null) {
                $formValues['short_description_en'] = $hintText;
            }

        } else {
            $stagesRun[] = 'gemini_vision';
            $extracted = $stage1Result['extracted'];
            $geminiConfidence = $extracted['confidence'] ?? [];
            if (!empty($extracted['warnings'])) {
                $warnings = array_merge($warnings, $extracted['warnings']);
            }
            // Build clean form values (strip meta keys)
            $geminiValues = $this->buildFormValues($extracted);
            
            // Merge Gemini values — Gemini overrides local parse since it has image context
            foreach ($geminiValues as $key => $value) {
                if ($value !== null) {
                    $formValues[$key] = $value;
                    $fieldConfidence[$key] = $geminiConfidence[$key] ?? 0.80;
                    $fieldSources[$key] = 'gemini_vision';
                }
            }
            // Fill remaining nulls from gemini schema
            foreach ($geminiValues as $key => $value) {
                if (!isset($formValues[$key])) {
                    $formValues[$key] = $value;
                }
            }
        }

        // ─── CONFIDENCE GATE ──────────────────────────────────────────
        $overallConfidence = $this->computeOverallConfidence($fieldConfidence);
        $missingRequired = $this->findMissingRequired($formValues);

        Log::info('[AI Pipeline] Stage 1 (Gemini Vision) complete', [
            'fields_extracted' => count(array_filter($formValues, fn($v) => $v !== null)),
            'null_fields' => count(array_filter($formValues, fn($v) => $v === null)),
            'overall_confidence' => $overallConfidence,
            'missing_required' => $missingRequired,
        ]);

        // ─── STAGE 2: Local Pinecone DB High-Confidence Consensus ────
        $feedResult = [
            'consensus_values' => [],
            'field_confidence' => [],
            'field_sources' => [],
            'top_matches' => [],
            'warnings' => [],
        ];

        $feedResult = $pineconeMatcher->matchAndBuildConsensus($formValues, $hintText);

        if (!empty($feedResult['warnings'])) {
            $warnings = array_merge($warnings, $feedResult['warnings']);
        }

        if (!empty($feedResult['consensus_values'])) {
            foreach ($feedResult['consensus_values'] as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $feedConfidence = (float) ($feedResult['field_confidence'][$field] ?? 0.95);
                $existingValue = $formValues[$field] ?? null;
                $existingConf = (float) ($fieldConfidence[$field] ?? 0.0);

                if ($existingValue === null || $existingValue === '') {
                    $formValues[$field] = $value;
                    $fieldConfidence[$field] = $feedConfidence;
                    $fieldSources[$field] = $feedResult['field_sources'][$field] ?? 'pinecone_database';
                    continue;
                }

                if ((string) $existingValue !== (string) $value) {
                    if ($feedConfidence >= 0.85 && $existingConf < 0.80) {
                        $formValues[$field] = $value;
                        $fieldConfidence[$field] = $feedConfidence;
                        $fieldSources[$field] = $feedResult['field_sources'][$field] ?? 'pinecone_override';
                        $needsConfirmation[] = $field;
                        $warnings[] = "Pinecone consensus replaced '{$field}' and marked it for confirmation.";
                    } else {
                        $needsConfirmation[] = $field;
                    }
                }
            }

            $stagesRun[] = 'pinecone_database_consensus';
        }

        // ─── FAST PATH: If Pinecone provided enough data, skip slow stages ─
        $pineconeFieldCount = count($feedResult['consensus_values'] ?? []);
        $pineconeMatchCount = count($feedResult['top_matches'] ?? []);
        $hasAllRequired = empty($missingRequired);
        $highConfidence = $overallConfidence >= self::CONFIDENCE_THRESHOLD;
        $useFastPath = $speedMode === 'fast'
            || ($pineconeFieldCount >= 10 && $pineconeMatchCount >= 1)
            || ($speedMode !== 'deep' && $hasAllRequired && $highConfidence);

        if ($useFastPath) {
            Log::info('[AI Pipeline] FAST PATH: Pinecone provided ' . $pineconeFieldCount . ' fields, skipping slow validation stages');
            $stagesRun[] = 'fast_path_pinecone';

            // In fast mode, only backfill required fields if they are missing.
            $shouldRunOpenAiFastFill = !empty($missingRequired);
            if ($shouldRunOpenAiFastFill) {
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

            // Skip cross-validation and ChatGPT validation
            $pineconeResult = [
                'similar_boats'  => [],
                'anomalies'      => [],
                'anomaly_fields' => [],
            ];
            $removedFields = [];
            $mergeResult = [
                'form_values'        => $formValues,
                'field_confidence'   => $fieldConfidence,
                'removed_fields'     => [],
                'needs_confirmation' => [],
                'validation_notes'   => $shouldRunOpenAiFastFill
                    ? 'Fast path: returned core extraction quickly and backfilled missing required fields.'
                    : 'Fast path: returned core extraction quickly with Pinecone consensus.',
            ];

            $stagesRun[] = 'confidence_merge';
            $overallConfidence = $this->computeOverallConfidence($fieldConfidence);

        } else {
        // ─── SLOW PATH: Full enrichment + validation pipeline ─────────

        // ─── ENRICHMENT STAGES (Gemini DB & OpenAI World Knowledge) ───
        $nullCount = count(array_filter($formValues, fn($v) => $v === null));
        if ($nullCount > 0) {
            // First try Gemini DB enrichment
            $geminiEnriched = $this->runEnrichment($formValues, $fieldConfidence, $apiKey);
            if (!empty($geminiEnriched)) {
                $stagesRun[] = 'gemini_db_enrichment';
                foreach ($geminiEnriched as $key => $val) {
                    // check if the key is actually in formValues, else add it
                    if ($val !== null && (!isset($formValues[$key]) || $formValues[$key] === null)) {
                        $formValues[$key] = $val;
                        $fieldConfidence[$key] = 0.60;
                        $fieldSources[$key] = 'gemini_db_enrichment';
                    }
                }
            }

            // Re-check nulls, try OpenAI World Knowledge for remaining
            $nullCount = count(array_filter($formValues, fn($v) => $v === null));
            if ($nullCount > 0) {
                $openAiKeyEnv = config('services.openai.key');
                if (!empty($openAiKeyEnv)) {
                    $openaiEnriched = $this->runOpenAiEnrichment($formValues, $fieldConfidence, $openAiKeyEnv, []);
                    if (!empty($openaiEnriched)) {
                        $stagesRun[] = 'openai_world_knowledge_enrichment';
                        foreach ($openaiEnriched as $key => $val) {
                            if ($val !== null && (!isset($formValues[$key]) || $formValues[$key] === null)) {
                                $formValues[$key] = $val;
                                $fieldConfidence[$key] = 0.50; // Inferred from world knowledge
                                $fieldSources[$key] = 'openai_world_knowledge_enrichment';
                            }
                        }
                    }
                }
            }
        }

        // ─── STAGE 3: Pinecone Cross-Validation ──────────────────────
        $pineconeResult = [
            'similar_boats'  => [],
            'anomalies'      => [],
            'anomaly_fields' => [],
        ];

        if (!empty(array_filter($formValues, fn($v) => $v !== null))) {
            $pineconeResult = $this->runPineconeCrossValidation($formValues, $fieldConfidence);
            if (!empty($pineconeResult['similar_boats'])) {
                $stagesRun[] = 'pinecone_cross_validation';
            }
            if (!empty($pineconeResult['anomalies'])) {
                foreach ($pineconeResult['anomalies'] as $anomaly) {
                    $warnings[] = "⚠️ Anomaly: {$anomaly['message']}";
                }
            }
        }

        // ─── STAGE 4: ChatGPT Validation (Logic + Visual Cross-Check) ─
        $validationResult = [
            'confirmed_fields' => [],
            'removed_fields'   => [],
            'adjusted_values'  => [],
            'suggested_additions' => [],
            'adjusted_confidence' => [],
            'notes'            => '',
        ];

        $validationResult = $this->runChatGptValidation(
            $formValues,
            $fieldConfidence,
            $pineconeResult,
            $feedResult,
            $request
        );
        if (!empty($validationResult['confirmed_fields']) || !empty($validationResult['removed_fields'])) {
            $stagesRun[] = 'chatgpt_validation';
        }

        // ─── STAGE 5: Confidence-Based Merge ─────────────────────────
        $mergeResult = $this->mergeWithConfidence(
            $formValues,
            $fieldConfidence,
            $validationResult,
            $pineconeResult
        );

        $formValues      = $mergeResult['form_values'];
        $fieldConfidence  = $mergeResult['field_confidence'];
        $removedFields    = $mergeResult['removed_fields'];
        $needsConfirmation = array_values(array_unique(array_merge($needsConfirmation, $mergeResult['needs_confirmation'])));
        $stagesRun[] = 'confidence_merge';

        $overallConfidence = $this->computeOverallConfidence($fieldConfidence);

        } // end slow path

        Log::info('[AI Pipeline] Pipeline complete', [
            'stages'              => $stagesRun,
            'total_fields_filled' => count(array_filter($formValues, fn($v) => $v !== null)),
            'fields_removed'      => count($removedFields),
            'needs_confirmation'  => $needsConfirmation,
            'anomalies_detected'  => count($pineconeResult['anomalies']),
            'overall_confidence'  => $overallConfidence,
        ]);

        return response()->json([
            'success' => true,
            'step2_form_values' => $formValues,
            'meta' => [
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
                'speed_mode'              => $speedMode,
                'vision_images_used'      => $visionImagesUsed,
            ],
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
        $imageCount = 0;
        
        if ($request->has('yacht_id')) {
            $yachtId = $request->input('yacht_id');
            $images = \App\Models\YachtImage::where('yacht_id', $yachtId)
                ->approved()
                ->orderBy('sort_order')
                ->limit($maxVisionImages)
                ->get();
            foreach ($images as $yachtImage) {
                try {
                    // Extract relative path from URL
                    $path = str_replace(url('storage') . '/', '', $yachtImage->optimized_master_url);
                    $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
                    
                    if (file_exists($fullPath)) {
                        $imageData = base64_encode(file_get_contents($fullPath));
                        $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data'      => $imageData,
                            ]
                        ];
                        $imageCount++;
                    }
                } catch (\Exception $e) {
                    Log::warning("[AI Pipeline] Failed to read db image: " . $e->getMessage());
                }
            }
        } elseif ($request->hasFile('images')) {
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
HINT];
        } else {
            $parts[] = ['text' => "No seller text provided. Extract ONLY what is visible in the images above."];
        }

        try {
            // Retry logic for Gemini 429 rate limits
            $maxRetries = 3;
            $response = null;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $response = Http::timeout(25)->post($endpoint, [
                    'contents' => [['parts' => $parts]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature'      => 0.1,
                    ],
                ]);

                if ($response->successful()) {
                    break;
                }

                if ($response->status() === 429 && $attempt < $maxRetries) {
                    $waitSeconds = pow(2, $attempt); // 2s, 4s
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
                $response = Http::timeout(20)->post($endpoint, [
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
            $targetFields = !empty($missingImportant)
                ? array_values(array_intersect($nullFields, $missingImportant))
                : $nullFields;

            if (empty($targetFields)) {
                return null;
            }

            $nullFieldsList = implode(', ', $targetFields);

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
- Return ONLY valid JSON containing the fields you can fill, plus "confidence" object and "warnings" array.

PARTIAL DATA ALREADY KNOWN:
{$partialData}

FIELDS TO TRY TO FILL (focus on these first):
{$nullFieldsList}
PROMPT;

            $endpoint = 'https://api.openai.com/v1/chat/completions';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(20)->post($endpoint, [
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
                Log::warning('[AI Pipeline] Failed to parse OpenAI enrichment JSON');
                return null;
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
        return <<<'SCHEMA'
You are a STRICT boat data extraction agent. You extract ONLY verifiable data.

🚨 ANTI-HALLUCINATION RULES (CRITICAL — violating these is a FAILURE):
1. If you CANNOT see an object/feature in ANY image → return null, NEVER "Yes"
2. NEVER infer equipment from "general boat knowledge" — if you can't see a microwave, don't claim one exists
3. Colors: report ONLY what you SEE. If hull looks grey, say grey. Do NOT guess "white" because it's typical.
4. Boat type: determine ONLY from visible features:
   - Mast/sails visible → sailboat
   - No mast, engine visible → motorboat
   - Two hulls → catamaran
   - Inflatable tubes → rib
   - If unclear → null
5. For EVERY field you fill, you MUST be able to point to the evidence (image region or text)
6. NEVER fill boolean equipment fields (heating, air_conditioning, etc.) as true unless you SEE evidence

DATA SOURCES AND CONFIDENCE RULES:
- Text/label clearly readable in image → confidence 0.95
- Seller-provided hint text states it → confidence 0.90
- Object/feature clearly visible in image → confidence 0.85
- Partially visible or obscured → confidence 0.75
- Below 0.75 confidence → DO NOT INCLUDE, set field to null

FOR EACH FIELD: Include a confidence score in the "confidence" object.
If you set a field to a value, its confidence MUST be ≥ 0.75 or you MUST set it to null instead.

Return this exact JSON structure:
{
  "boat_name": "string|null",
  "manufacturer": "string|null (ONLY if brand name/logo visible or in seller text)",
  "model": "string|null (ONLY if model name visible or in seller text)",
  "boat_type": "string|null (sailboat/motorboat/catamaran/rib/trawler/sloop/other — based on visual features ONLY)",
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
  "hull_colour": "string|null (ONLY the color you SEE in images)",
  "hull_construction": "string|null (GRP/steel/aluminum/wood/composite — ONLY if visible or stated)",
  "hull_type": "string|null (mono/catamaran/trimaran — based on hull count visible)",
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
  "flybridge": "boolean|null (true ONLY if flybridge clearly visible)",
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
  "cabins": "string|null (ONLY if cabin count visible or stated)",
  "berths": "string|null",
  "toilet": "string|null",
  "shower": "string|null",
  "bath": "string|null",
  "heating": "boolean|null (ONLY true if heating system visible)",
  "air_conditioning": "boolean|null (ONLY true if AC units visible)",
  "ce_category": "string|null (A/B/C/D)",
  "passenger_capacity": "number|null",
  "compass": "string|null (ONLY if visible in images)",
  "gps": "string|null (ONLY if GPS unit visible)",
  "radar": "string|null (ONLY if radar dome/mount visible)",
  "autopilot": "string|null",
  "vhf": "string|null (ONLY if VHF radio visible)",
  "plotter": "string|null",
  "depth_instrument": "string|null",
  "wind_instrument": "string|null",
  "speed_instrument": "string|null",
  "navigation_lights": "string|null",
  "life_raft": "string|null (ONLY if life raft container visible)",
  "epirb": "string|null",
  "fire_extinguisher": "string|null (ONLY if visible)",
  "bilge_pump": "string|null",
  "mob_system": "string|null",
  "life_jackets": "string|null",
  "radar_reflector": "string|null",
  "flares": "string|null",
  "battery": "string|null",
  "battery_charger": "string|null",
  "generator": "string|null",
  "inverter": "string|null",
  "shorepower": "string|null",
  "solar_panel": "string|null (ONLY if solar panels visible on deck)",
  "wind_generator": "string|null",
  "voltage": "string|null",
  "anchor": "string|null (ONLY if anchor visible)",
  "anchor_winch": "string|null",
  "bimini": "string|null (ONLY if bimini top visible)",
  "spray_hood": "string|null",
  "swimming_platform": "string|null (ONLY if swim platform visible)",
  "swimming_ladder": "string|null",
  "teak_deck": "string|null (ONLY if teak deck visible)",
  "cockpit_table": "string|null",
  "dinghy": "string|null (ONLY if dinghy visible)",
  "covers": "string|null",
  "spinnaker": "string|null",
  "fenders": "string|null",
  "television": "string|null (ONLY if TV screen visible)",
  "cd_player": "string|null",
  "dvd_player": "string|null",
  "satellite_reception": "string|null",
  "oven": "string|null (ONLY if oven visible in galley photo)",
  "microwave": "string|null (ONLY if microwave visible in galley photo)",
  "fridge": "string|null (ONLY if fridge visible)",
  "freezer": "string|null",
  "cooker": "string|null (ONLY if cooktop/stove visible)",
  "owners_comment": "string|null (any visible seller notes)",
  "reg_details": "string|null (registration number/country)",
  "known_defects": "string|null",
  "last_serviced": "string|null",
  "short_description_en": "string (2-3 sentence summary based ONLY on confirmed data)",
  "short_description_nl": "string (Dutch translation of the English summary)",
  "short_description_de": "string (German translation of the English summary)",
  "warnings": ["array of strings — flag uncertain detections, contradictions between images, unreadable text"],
  "confidence": {
    "field_name": "0.0 to 1.0 — EVERY non-null field MUST have a confidence entry ≥ 0.75"
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

            // Build messages for GPT-4o
            $messages = [];

            // System prompt: strict validator
            $messages[] = [
                'role'    => 'system',
                'content' => <<<VALIDATOR
You are a YACHT DATA VALIDATOR for NauticSecure. Your job is to VALIDATE the data and REMOVE CONTRADICTIONS, but PRESERVE INFERRED FACTORY SPECS.

VALIDATION RULES:
1. DO NOT remove fields arbitrarily. The goal is to aggressively fill as much data as possible, so ACCEPT inferred equipment and factory specs. If the make/model is known, standard specs provided by enrichment sources MUST be retained.
2. Do NOT remove fields with lower confidence if they represent reasonable inferred factory standard specs. Only remove flagrant hallucinations that physically contradict the images.
3. If Pinecone anomaly detected for a field (most similar boats disagree), REDUCE that field's confidence by 0.20.
4. Cross-check: if boat_type = "sailboat" but no mast visible → flag/adjust.
5. Cross-check: if hull_colour claimed but contradicts what most images show → flag/remove.
6. For equipment fields (microwave, fridge, GPS, etc.): if they are standard for the identified boat type/size, keep them. Do NOT delete them just because they aren't visible in the photos.
7. If the data contains an EXTREMELY HIGH CONFIDENCE match from the Local Pinecone DB, ALWAYS trust the Pinecone DB values, as they are the source of truth for the payload.

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
            ])->timeout(25)->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4o-mini',
                'messages'        => $messages,
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0.1, // very low for deterministic validation
                'max_tokens'      => 2000,
            ]);

            if (!$response->successful()) {
                Log::warning('[AI Pipeline] ChatGPT validation failed: ' . $response->status() . ' ' . $response->body());
                return $result;
            }

            $body = $response->json();
            $text = $body['choices'][0]['message']['content'] ?? null;

            if (!$text) {
                return $result;
            }

            $validated = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('[AI Pipeline] Failed to parse ChatGPT validation JSON');
                return $result;
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

    /**
     * Generate custom descriptions based on tone and word count settings.
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

        // Build context from the yacht data, excluding internal/visual fields to save tokens
        $skipFields = ['images', 'created_at', 'updated_at', 'location_lat', 'location_lng'];
        $filteredData = collect($yacht->toArray())
            ->except($skipFields)
            ->filter(fn($val) => $val !== null && $val !== '')
            ->toArray();
        $yachtData = json_encode($filteredData);

        $systemPrompt = <<<PROMPT
You are an expert yacht copywriter. Given the data of a yacht, generate an engaging, descriptive marketing summary.
Follow these requirements STRICTLY:
- Tone: {$tone}
- Length: Ensure the word count is between {$minWords} and {$maxWords} words per language. Do not output less than {$minWords} words!
- Language: Provide the description in English, Dutch, and German.
- Focus on the key features, amenities, and unique selling points of the boat.
- Return ONLY a JSON object with keys "en", "nl", and "de".
PROMPT;

        $endpoint = 'https://api.openai.com/v1/chat/completions';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $openAiKey,
            ])->timeout(45)->post($endpoint, [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "Yacht Data:\n" . $yachtData]
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

            if (!$response->successful()) {
                Log::error('[AI Description] API error: ' . $response->body());
                return response()->json(['error' => 'OpenAI API failed'], 500);
            }

            $body = $response->json();
            $text = $body['choices'][0]['message']['content'] ?? null;
            
            if (!$text) {
                return response()->json(['error' => 'Empty response from OpenAI'], 500);
            }

            $extracted = json_decode($text, true);

            return response()->json([
                'success' => true,
                'descriptions' => [
                    'en' => $extracted['en'] ?? null,
                    'nl' => $extracted['nl'] ?? null,
                    'de' => $extracted['de'] ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[AI Description] Exception: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

        return response()->json($result);
    }
}
