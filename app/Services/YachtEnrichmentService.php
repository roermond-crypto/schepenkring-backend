<?php

namespace App\Services;

use App\Models\Yacht;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YachtEnrichmentService
{
    private ?string $openAiKey;

    public function __construct()
    {
        $this->openAiKey = config('services.openai.key');
    }

    /**
     * Enrich the given yacht using ChatGPT.
     *
     * @param  Yacht  $yacht
     * @return bool Success status
     */
    public function enrich(Yacht $yacht): bool
    {
        if (!$this->openAiKey) {
            Log::error('[YachtEnrichment] Missing OpenAI API Key');
            return false;
        }

        $yacht->loadMissing(['images', 'dimensions', 'construction', 'accommodation', 'engine', 'electrical', 'navigation', 'safety', 'comfort', 'deckEquipment', 'rigging']);

        $rawData = $yacht->toArray();
        
        // Prepare prompt
        $prompt = $this->buildPrompt($rawData);

        try {
            $response = Http::withToken($this->openAiKey)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a professional yacht broker and data scientist. Your job is to normalize raw scraped boat data into a clean, structured format. Clean HTML, fix terminology, and ensure consistency.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2,
                ]);

            if ($response->failed()) {
                Log::error('[YachtEnrichment] OpenAI request failed: ' . $response->status());
                return false;
            }

            $result = $response->json('choices.0.message.content');
            $data = json_decode($result, true);

            if (!$data) {
                Log::error('[YachtEnrichment] Failed to decode JSON from OpenAI');
                return false;
            }

            $this->applyEnrichedData($yacht, $data);

            return true;

        } catch (\Exception $e) {
            Log::error('[YachtEnrichment] Exception: ' . $e->getMessage());
            return false;
        }
    }
    private function buildPrompt(array $rawData): string
    {
        // Simple representation of the boat for the AI
        $boatInfo = [
            'title' => $rawData['boat_name'] ?? 'Unknown',
            'manufacturer' => $rawData['manufacturer'] ?? null,
            'model' => $rawData['model'] ?? null,
            'year' => $rawData['year'] ?? null,
            'description' => $rawData['short_description_nl'] ?? null,
            'specs' => array_intersect_key($rawData, array_flip([
                'loa', 'beam', 'draft', 'engine_manufacturer', 'engine_quantity', 'fuel',
                'boat_type', 'boat_category', 'vessel_lying'
            ]))
        ];

        $inputData = json_encode($boatInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Return a JSON object containing normalized boat data.
Clean the description from any HTML. 
Generate a short summary (en, nl).
Identify key features.
Correct any shipyard/model names if they look misspelled.

Input Data:
{$inputData}

Expected JSON Schema:
{
  "normalized_fields": {
    "manufacturer": "string",
    "model": "string",
    "boat_type": "string",
    "boat_category": "string",
    "year": integer
  },
  "cleaned_descriptions": {
    "nl": "string",
    "en": "string"
  },
  "ai_summary": {
    "nl": "string",
    "en": "string"
  },
  "key_features": ["string"],
  "structured_specs": {
    "length_m": float,
    "beam_m": float,
    "draft_m": float,
    "engine_info": "string"
  }
}
PROMPT;
    }

    private function applyEnrichedData(Yacht $yacht, array $data)
    {
        // Update core fields
        if (isset($data['normalized_fields'])) {
            $yacht->fill($data['normalized_fields']);
        }

        if (isset($data['cleaned_descriptions']['nl'])) {
            $yacht->short_description_nl = $data['cleaned_descriptions']['nl'];
        }
        
        if (isset($data['cleaned_descriptions']['en'])) {
            $yacht->short_description_en = $data['cleaned_descriptions']['en'];
        }

        // Store AI summary and key features in a specific location if columns exist,
        // otherwise we might need to add them or use owners_comment/short_description.
        // For now, let's prepend key features to the description or store in a JSON field if available.
        // Looking at Yacht.php, we don't have dedicated 'ai_summary' column.
        // I'll update owners_comment with the summary.
        if (isset($data['ai_summary']['nl'])) {
            $yacht->owners_comment = $data['ai_summary']['nl'];
        }

        $yacht->save();

        // Update sub-tables with normalized specs
        if (isset($data['structured_specs'])) {
            $specs = $data['structured_specs'];
            $flatSpecs = [
                'loa' => $specs['length_m'] ?? null,
                'beam' => $specs['beam_m'] ?? null,
                'draft' => $specs['draft_m'] ?? null,
                'motorization_summary' => $specs['engine_info'] ?? null,
            ];
            $yacht->saveSubTables(array_filter($flatSpecs));
        }
    }
}
