<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PineconeMatcherService
{
    private string $openAiKey;
    private string $pineconeKey;
    private string $pineconeHost;

    public function __construct()
    {
        $this->openAiKey = (string) config('services.openai.key');
        $this->pineconeKey = (string) config('services.pinecone.key');
        $this->pineconeHost = rtrim((string) config('services.pinecone.host'), '/');
    }

    /**
     * Match a set of partial boat details against the local Pinecone DB.
     * Returns the full reconstructed Step 2 form values + confident scores if it finds a high match.
     */
    public function matchAndBuildConsensus(array $formValues, ?string $hintText): array
    {
        $warnings = [];

        if ($this->openAiKey === '' || $this->pineconeKey === '' || $this->pineconeHost === '') {
            $warnings[] = "Pinecone matcher skipped: API keys are missing in .env.";
            return $this->getEmptyResponse($warnings);
        }

        // 1. Build context search string using whatever data we have so far
        $searchText = collect([
            $formValues['manufacturer'] ?? null,
            $formValues['model'] ?? null,
            $formValues['boat_type'] ?? null,
            $formValues['boat_name'] ?? null,
            $formValues['hull_construction'] ?? null,
            $formValues['fuel'] ?? null,
            $formValues['year'] ?? null,
            $hintText ?? null,
        ])->filter()->implode(' ');

        if (strlen($searchText) < 3) {
            $warnings[] = "Not enough data to query Pinecone.";
            return $this->getEmptyResponse($warnings);
        }

        // 2. Generate OpenAI Embeddings for this text
        try {
            $embedResponse = Http::withToken($this->openAiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => 'text-embedding-3-small',
                    'input' => $searchText,
                    'dimensions' => 1408,
                ]);

            if (!$embedResponse->successful()) {
                Log::warning('[PineconeMatcher] Embedding failed: ' . $embedResponse->status());
                $warnings[] = "Embedding failed during Pinecone search.";
                return $this->getEmptyResponse($warnings);
            }

            $vector = $embedResponse->json('data.0.embedding');
            if (!$vector) {
                return $this->getEmptyResponse($warnings);
            }

            // 3. Query Pinecone top match
            $pineconeUrl = str_starts_with($this->pineconeHost, 'http')
                ? $this->pineconeHost . '/query'
                : 'https://' . $this->pineconeHost . '/query';

            $pineconeResponse = Http::withHeaders([
                'Api-Key'      => $this->pineconeKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($pineconeUrl, [
                'vector'          => $vector,
                'topK'            => 1,
                'includeMetadata' => true,
                'filter'          => [
                    'full_payload_sha256' => ['$exists' => true]
                ]
            ]);

            if (!$pineconeResponse->successful()) {
                Log::warning('[PineconeMatcher] Pinecone query failed: ' . $pineconeResponse->status() . ' Body: ' . $pineconeResponse->body());
                $warnings[] = "Pinecone query failed: " . $pineconeResponse->body();
                return $this->getEmptyResponse($warnings);
            }

            $matches = $pineconeResponse->json('matches') ?? [];
            if (empty($matches)) {
                $warnings[] = "No highly similar boats found in Pinecone.";
                return $this->getEmptyResponse($warnings);
            }

            $topMatch = $matches[0];
            $score = $topMatch['score'] ?? 0;

            // Optional minimum threshold - you can adjust this based on testing
            // 0.40 since embeddings between user partial text and full XML JSON might be lower, but it is often still the correct boat.
            if ($score < 0.35) {
                $warnings[] = "Top match score ({$score}) was too low to confidently use.";
                return $this->getEmptyResponse($warnings);
            }

            // 4. Decode the GZIP Base64 Payload hidden in metadata
            $metadata = $topMatch['metadata'] ?? [];
            $b64Payload = $metadata['full_payload_gzip_b64'] ?? null;

            if (!$b64Payload) {
                $warnings[] = "Pinecone match had no original compressed JSON payload.";
                return $this->getEmptyResponse($warnings);
            }

            $compressed = base64_decode($b64Payload);
            if ($compressed === false) {
                $warnings[] = "Failed to base64 decode payload.";
                return $this->getEmptyResponse($warnings);
            }

            // Attempt to decompress gzip (fallback to raw if gzdecode fails)
            $jsonString = @gzdecode($compressed);
            if ($jsonString === false) {
                $jsonString = $compressed; 
            }

            $rawBoat = json_decode($jsonString, true);
            if (!$rawBoat || !isset($rawBoat['advert'])) {
                $warnings[] = "Failed to parse JSON payload.";
                return $this->getEmptyResponse($warnings);
            }

            $advert = $rawBoat['advert'];
            
            // 5. Build parsed final form values out of the original XML JSON
            $parsedValues = $this->parseAdvertPayload($advert);
            
            $consensusValues = [];
            $fieldConfidence = [];
            $fieldSources = [];

            // Add these fields to consensus with 0.95 confidence
            foreach ($parsedValues as $field => $val) {
                if ($val !== null && $val !== '') {
                    $consensusValues[$field] = $val;
                    $fieldConfidence[$field] = 0.95; // We consider Pinecone db payload to be absolute truth
                    $fieldSources[$field] = 'pinecone_database';
                }
            }

            return [
                'consensus_values' => $consensusValues,
                'field_confidence' => $fieldConfidence,
                'field_sources'    => $fieldSources,
                'top_matches'      => [['score' => $score, 'boat' => $parsedValues, 'source_url' => 'pinecone']],
                'warnings'         => $warnings,
            ];

        } catch (\Throwable $e) {
            Log::error('[PineconeMatcher] exception: ' . $e->getMessage());
            $warnings[] = "Exception searching Pinecone: " . $e->getMessage();
            return $this->getEmptyResponse($warnings);
        }
    }

    private function getEmptyResponse(array $warnings): array
    {
        return [
            'consensus_values' => [],
            'field_confidence' => [],
            'field_sources'    => [],
            'top_matches'      => [],
            'warnings'         => $warnings,
        ];
    }

    private function parseAdvertPayload(array $advert): array
    {
        $features = $advert['advert_features'] ?? [];
        $boatFeatures = $advert['boat_features'] ?? [];
        $descriptions = $advert['advert_descriptions'] ?? [];

        $boat = [];

        // ─── Advert-level features ───────────────────────────────────
        $boat['manufacturer']   = $this->trimOrNull($features['manufacturer'] ?? '');
        $boat['model']          = $this->trimOrNull($features['model'] ?? '');
        $boat['boat_type']      = $this->trimOrNull($features['boat_type'] ?? '');
        $boat['boat_category']  = $this->trimOrNull($features['boat_category'] ?? '');
        $boat['new_or_used']    = $this->trimOrNull($features['new_or_used'] ?? '');
        $boat['price']          = $this->toNumber($features['asking_price'] ?? '');
        $boat['where']          = $this->trimOrNull($features['vessel_lying'] ?? '');
        $boat['boat_name']      = $this->getFeature($boatFeatures, 'boat_name') 
                                 ?? $this->getFeature($boatFeatures, 'boat_name', 'build');

        // ─── Dimensions ──────────────────────────────────────────────
        $boat['loa']       = $this->getDimensionMeters($boatFeatures, 'loa');
        $boat['lwl']       = $this->getDimensionMeters($boatFeatures, 'lwl');
        $boat['beam']      = $this->getDimensionMeters($boatFeatures, 'beam');
        $boat['draft']     = $this->getDimensionMeters($boatFeatures, 'draft');
        $boat['air_draft'] = $this->getDimensionMeters($boatFeatures, 'air_draft')
                            ?? $this->getDimensionMeters($boatFeatures, 'air_draf'); // typo in some XMLs

        // ─── Build section ───────────────────────────────────────────
        $boat['year']                         = $this->toNumber($this->getFeature($boatFeatures, 'year', 'build'));
        $boat['hull_colour']                  = $this->getFeature($boatFeatures, 'hull_colour', 'build');
        $boat['hull_construction']            = $this->getFeature($boatFeatures, 'hull_construction', 'build');
        $boat['hull_type']                    = $this->getFeature($boatFeatures, 'hull_type', 'build');
        $boat['hull_number']                  = $this->getFeature($boatFeatures, 'hull_number', 'build');
        $boat['designer']                     = $this->getFeature($boatFeatures, 'designer', 'build');
        $boat['builder']                      = $this->getFeature($boatFeatures, 'builder', 'build');
        $boat['super_structure_colour']       = $this->getFeature($boatFeatures, 'super_structure_colour', 'build');
        $boat['super_structure_construction'] = $this->getFeature($boatFeatures, 'super_structure_construction', 'build');
        $boat['deck_colour']                  = $this->getFeature($boatFeatures, 'deck_colour', 'build');
        $boat['deck_construction']            = $this->getFeature($boatFeatures, 'deck_construction', 'build');
        $boat['cockpit_type']                 = $this->getFeature($boatFeatures, 'cockpit_type', 'build');
        $boat['control_type']                 = $this->getFeature($boatFeatures, 'control_type', 'build');
        $boat['flybridge']                    = $this->getFeatureYesNo($boatFeatures, 'flybridge', 'build');
        $boat['displacement']                 = $this->toNumber($this->getFeature($boatFeatures, 'displacement', 'build'));
        $boat['ballast']                      = $this->toNumber($this->getFeature($boatFeatures, 'ballast', 'build'));
        $boat['keel_type']                    = $this->getFeature($boatFeatures, 'keel_type', 'build');

        // ─── Engine section ──────────────────────────────────────────
        $boat['engine_manufacturer'] = $this->getFeature($boatFeatures, 'engine_manufacturer', 'engine');
        $boat['fuel']                = $this->getFeature($boatFeatures, 'fuel', 'engine');
        $boat['horse_power']         = $this->toNumber($this->getFeature($boatFeatures, 'horse_power', 'engine'));
        $boat['hours']               = $this->toNumber($this->getFeature($boatFeatures, 'hours', 'engine'));
        $boat['cruising_speed']      = $this->toNumber($this->getFeature($boatFeatures, 'cruising_speed', 'engine'));
        $boat['max_speed']           = $this->toNumber($this->getFeature($boatFeatures, 'max_speed', 'engine'));
        $boat['engine_quantity']     = $this->toNumber($this->getFeature($boatFeatures, 'engine_quantity', 'engine'));
        $boat['drive_type']          = $this->getFeature($boatFeatures, 'drive_type', 'engine')
                                      ?? $this->getFeature($boatFeatures, 'starting_type', 'engine');
        $boat['propulsion']          = $this->getFeature($boatFeatures, 'propulsion', 'engine')
                                      ?? $this->getFeature($boatFeatures, 'cooling_system', 'engine');

        // ─── Galley section ──────────────────────────────────────────
        $boat['oven']             = $this->getFeatureYesNo($boatFeatures, 'oven', 'galley');
        $boat['microwave']        = $this->getFeatureYesNo($boatFeatures, 'microwave', 'galley');
        $boat['fridge']           = $this->getFeatureYesNo($boatFeatures, 'fridge', 'galley');
        $boat['freezer']          = $this->getFeatureYesNo($boatFeatures, 'freezer', 'galley');
        $boat['heating']          = $this->getFeatureYesNo($boatFeatures, 'heating', 'galley');
        $boat['air_conditioning'] = $this->getFeatureYesNo($boatFeatures, 'air_conditioning', 'galley');
        $boat['cooker']           = $this->getFeatureYesNo($boatFeatures, 'cooker', 'galley');

        // ─── Accommodation section ───────────────────────────────────
        $boat['cabins'] = $this->toNumber($this->getFeature($boatFeatures, 'cabins', 'accommodation'));
        $boat['berths'] = $this->toNumber($this->getFeature($boatFeatures, 'berths', 'accommodation'));
        $boat['toilet'] = $this->getFeatureYesNo($boatFeatures, 'toilet', 'accommodation');
        $boat['shower'] = $this->getFeatureYesNo($boatFeatures, 'shower', 'accommodation');
        $boat['bath']   = $this->getFeatureYesNo($boatFeatures, 'bath', 'accommodation');

        // ─── Navigation section ──────────────────────────────────────
        $boat['navigation_lights'] = $this->getFeatureYesNo($boatFeatures, 'navigation_lights', 'navigation');
        $boat['compass']           = $this->getFeatureYesNo($boatFeatures, 'compass', 'navigation');
        $boat['depth_instrument']  = $this->getFeatureYesNo($boatFeatures, 'depth_instrument', 'navigation');
        $boat['wind_instrument']   = $this->getFeatureYesNo($boatFeatures, 'wind_instrument', 'navigation');
        $boat['autopilot']         = $this->getFeatureYesNo($boatFeatures, 'autopilot', 'navigation');
        $boat['gps']               = $this->getFeatureYesNo($boatFeatures, 'gps', 'navigation');
        $boat['vhf']               = $this->getFeatureYesNo($boatFeatures, 'vhf', 'navigation');
        $boat['plotter']           = $this->getFeatureYesNo($boatFeatures, 'plotter', 'navigation');
        $boat['speed_instrument']  = $this->getFeatureYesNo($boatFeatures, 'speed_instrument', 'navigation');
        $boat['radar']             = $this->getFeatureYesNo($boatFeatures, 'radar', 'navigation');

        // ─── Safety Equipment section ────────────────────────────────
        $boat['life_raft']          = $this->getFeatureYesNo($boatFeatures, 'life_raft', 'safety_equipment');
        $boat['epirb']              = $this->getFeatureYesNo($boatFeatures, 'epirb', 'safety_equipment');
        $boat['bilge_pump']         = $this->getFeatureYesNo($boatFeatures, 'bilge_pump', 'safety_equipment');
        $boat['fire_extinguisher']  = $this->getFeatureYesNo($boatFeatures, 'fire_extinguisher', 'safety_equipment');
        $boat['mob_system']         = $this->getFeatureYesNo($boatFeatures, 'mob_system', 'safety_equipment');
        $boat['life_jackets']       = $this->getFeatureYesNo($boatFeatures, 'life_jackets', 'safety_equipment');
        $boat['radar_reflector']    = $this->getFeatureYesNo($boatFeatures, 'radar_reflector', 'safety_equipment');
        $boat['flares']             = $this->getFeatureYesNo($boatFeatures, 'flares', 'safety_equipment');

        // ─── Electronics section ─────────────────────────────────────
        $boat['battery']         = $this->getFeatureYesNo($boatFeatures, 'battery', 'electronics');
        $boat['battery_charger'] = $this->getFeatureYesNo($boatFeatures, 'battery_charger', 'electronics');
        $boat['generator']       = $this->getFeatureYesNo($boatFeatures, 'generator', 'electronics');
        $boat['inverter']        = $this->getFeatureYesNo($boatFeatures, 'inverter', 'electronics');
        $boat['shorepower']      = $this->getFeatureYesNo($boatFeatures, 'shorepower', 'electronics');
        $boat['solar_panel']     = $this->getFeatureYesNo($boatFeatures, 'solar_panel', 'electronics');
        $boat['wind_generator']  = $this->getFeatureYesNo($boatFeatures, 'wind_generator', 'electronics');
        $boat['voltage']         = $this->toNumber($this->getFeature($boatFeatures, 'voltage', 'electronics'));

        // ─── General / Entertainment section ─────────────────────────
        $boat['television']          = $this->getFeatureYesNo($boatFeatures, 'television', 'general');
        $boat['cd_player']           = $this->getFeatureYesNo($boatFeatures, 'cd_player', 'general');
        $boat['dvd_player']          = $this->getFeatureYesNo($boatFeatures, 'dvd_player', 'general');
        $boat['satellite_reception'] = $this->getFeatureYesNo($boatFeatures, 'satellite_reception', 'general');

        // ─── Equipment section ───────────────────────────────────────
        $boat['anchor']            = $this->getFeatureYesNo($boatFeatures, 'anchor', 'equipment');
        $boat['anchor_winch']      = $this->getFeatureYesNo($boatFeatures, 'anchor_winch', 'equipment');
        $boat['spray_hood']        = $this->getFeatureYesNo($boatFeatures, 'spray_hood', 'equipment');
        $boat['bimini']            = $this->getFeatureYesNo($boatFeatures, 'bimini', 'equipment');
        $boat['swimming_platform'] = $this->getFeatureYesNo($boatFeatures, 'swimming_platform', 'equipment');
        $boat['swimming_ladder']   = $this->getFeatureYesNo($boatFeatures, 'swimming_ladder', 'equipment');
        $boat['teak_deck']         = $this->getFeatureYesNo($boatFeatures, 'teak_deck', 'equipment');
        $boat['cockpit_table']     = $this->getFeatureYesNo($boatFeatures, 'cockpit_table', 'equipment');
        $boat['dinghy']            = $this->getFeatureYesNo($boatFeatures, 'dinghy', 'equipment');
        $boat['covers']            = $this->getFeatureYesNo($boatFeatures, 'covers', 'equipment');
        $boat['fenders']           = $this->getFeatureYesNo($boatFeatures, 'fenders', 'equipment');

        // ─── Rig & Sails section ─────────────────────────────────────
        $boat['spinnaker'] = $this->getFeatureYesNo($boatFeatures, 'spinnaker', 'rig_sails');

        // ─── Descriptions ────────────────────────────────────────────
        if (!empty($descriptions)) {
            // Look for English and Dutch descriptions
            $descItems = $descriptions['description'] ?? [];
            if (isset($descItems['language'])) {
                $descItems = [$descItems]; // single description
            }
            foreach ($descItems as $desc) {
                $lang = strtolower($desc['language'] ?? '');
                $text = $this->trimOrNull($desc['full_description'] ?? $desc['short_description'] ?? '');
                if ($text && (str_contains($lang, 'en') || $lang === 'english')) {
                    $boat['short_description_en'] = mb_substr($text, 0, 500);
                }
                if ($text && (str_contains($lang, 'nl') || str_contains($lang, 'dutch') || $lang === 'nederlands')) {
                    $boat['short_description_nl'] = mb_substr($text, 0, 500);
                }
            }
        }

        return array_filter($boat, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Get a feature value as Yes/No string.
     * Returns the raw value if it's a meaningful string (e.g., "Manual", "Electric").
     * Returns "Yes" if the field exists with a truthy/present value.
     * Returns null if the field doesn't exist in the XML.
     */
    private function getFeatureYesNo(array $boatFeatures, string $name, ?string $section = null): ?string
    {
        $value = $this->getFeature($boatFeatures, $name, $section);
        if ($value === null) {
            return null;
        }
        
        $lower = strtolower($value);
        // If it's a numeric count (like "2" for berths), return as-is
        if (is_numeric($value)) {
            return $value;
        }
        // If it has a descriptive value (e.g. "Manual", "Electric", "Diesel"), return it
        if (!in_array($lower, ['yes', 'no', 'true', 'false', '1', '0', ''])) {
            return $value;
        }
        // Standard yes/no
        if (in_array($lower, ['yes', 'true', '1'])) {
            return 'Yes';
        }
        if (in_array($lower, ['no', 'false', '0'])) {
            return 'No';
        }
        // If it just exists in the XML, it means "Yes"
        return 'Yes';
    }

    private function getFeature(array $boatFeatures, string $name, ?string $section = null): ?string
    {
        $parent = $section ? ($boatFeatures[$section] ?? $boatFeatures) : $boatFeatures;
        $items = $parent['item'] ?? [];

        // If 'item' is a single associative array rather than an array of items, wrap it
        if (isset($items['@attributes'])) {
            $items = [$items];
        }

        foreach ($items as $item) {
            if (isset($item['@attributes']['name']) && $item['@attributes']['name'] === $name) {
                return $this->trimOrNull($item['@value'] ?? $item['value'] ?? '');
            }
        }

        return null;
    }

    private function checkBooleanFeature(array $boatFeatures, string $name, ?string $section = null): bool
    {
        return $this->getFeature($boatFeatures, $name, $section) !== null;
    }

    private function getDimensionMeters(array $boatFeatures, string $name): ?float
    {
        $dimensions = $boatFeatures['dimensions']['item'] ?? [];
        if (isset($dimensions['@attributes'])) {
             $dimensions = [$dimensions];
        }

        foreach ($dimensions as $item) {
            if (isset($item['@attributes']['name']) && $item['@attributes']['name'] === $name) {
                $value = $this->toFloat($item['@value'] ?? $item['value'] ?? '');
                if ($value === null) return null;

                $unit = strtolower($item['@attributes']['unit'] ?? 'centimetres');
                if (str_contains($unit, 'cent')) {
                    return round($value / 100, 2);
                }
                return round($value, 2);
            }
        }

        return null;
    }

    private function toNumber($value)
    {
        $float = $this->toFloat($value);
        if ($float === null) {
            return null;
        }

        if (floor($float) == $float) {
            return (int) $float;
        }

        return round($float, 2);
    }

    private function toFloat($value): ?float
    {
        if (is_array($value)) {
            return null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.,-]/', '', $raw);
        if ($clean === '') {
            return null;
        }

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace(',', '', $clean);
        } else {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function trimOrNull($value): ?string
    {
        if (is_array($value)) {
           return null; // Ignore array edge cases if XML structure differs slightly
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
