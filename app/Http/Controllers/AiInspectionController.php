<?php

namespace App\Http\Controllers;

use App\Models\BoatCheck;
use App\Models\BoatInspection;
use App\Models\InspectionAnswer;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiInspectionController extends Controller
{
    /**
     * POST /api/inspections/{id}/ai-analyze
     *
     * Run AI analysis on all checklist questions for an inspection.
     * Gemini analyzes the boat's photos and specs per question.
     */
    public function analyze($id): JsonResponse
    {
        try {
            $inspection = BoatInspection::with('boat.images')->findOrFail($id);
            $yacht = $inspection->boat;

            if (!$yacht) {
                return response()->json(['error' => 'No boat linked to this inspection'], 404);
            }

            // Get checklist questions for this boat type
            $boatTypeId = $yacht->boat_type_id;
            $questions = BoatCheck::with('boatTypes')
                ->where(function ($q) use ($boatTypeId) {
                    $q->whereDoesntHave('boatTypes') // Generic (all types)
                      ->orWhereHas('boatTypes', function ($q2) use ($boatTypeId) {
                          $q2->where('boat_types.id', $boatTypeId);
                      });
                })
                ->get();

            if ($questions->isEmpty()) {
                return response()->json([
                    'message' => 'No checklist questions for this boat type',
                    'answers' => [],
                ]);
            }

            // Collect boat images
            $galleryImages = $yacht->images ?? collect();
            $imageDescriptions = [];
            foreach ($galleryImages as $img) {
                $imageDescriptions[] = [
                    'id' => $img->id,
                    'url' => $img->image_path ?? $img->path ?? '',
                    'category' => $img->category ?? 'General',
                ];
            }

            // Add main image
            if ($yacht->main_image) {
                array_unshift($imageDescriptions, [
                    'id' => 'main',
                    'url' => $yacht->main_image,
                    'category' => 'Main Photo',
                ]);
            }

            // Collect boat specs as JSON
            $boatSpecs = $yacht->only([
                'boat_name', 'year', 'loa', 'lwl', 'beam', 'draft', 'air_draft',
                'displacement', 'ballast', 'hull_type', 'hull_construction',
                'hull_colour', 'hull_number', 'designer', 'builder',
                'engine_manufacturer', 'horse_power', 'hours', 'fuel',
                'max_speed', 'cruising_speed', 'cabins', 'berths',
                'heating', 'cockpit_type', 'control_type',
            ]);

            $apiKey = env('GEMINI_API_KEY');
            if (!$apiKey) {
                return response()->json(['error' => 'GEMINI_API_KEY not configured'], 500);
            }

            $answers = [];

            foreach ($questions as $question) {
                try {
                    $result = $this->analyzeQuestion($question, $imageDescriptions, $boatSpecs, $yacht, $apiKey);

                    // Save or update the answer
                    $answer = InspectionAnswer::updateOrCreate(
                        [
                            'inspection_id' => $inspection->id,
                            'question_id' => $question->id,
                        ],
                        [
                            'ai_answer' => $result['answer'],
                            'ai_confidence' => $result['confidence'],
                            'ai_evidence' => $result['evidence'],
                            'review_status' => null, // Reset for fresh AI analysis
                        ]
                    );

                    $answer->load('question');
                    $answers[] = $answer;

                } catch (\Exception $e) {
                    Log::error("AI analysis failed for question {$question->id}: " . $e->getMessage());

                    // Still create an answer record with error
                    $answer = InspectionAnswer::updateOrCreate(
                        [
                            'inspection_id' => $inspection->id,
                            'question_id' => $question->id,
                        ],
                        [
                            'ai_answer' => 'Analysis failed',
                            'ai_confidence' => 0,
                            'ai_evidence' => ['error' => $e->getMessage()],
                        ]
                    );
                    $answer->load('question');
                    $answers[] = $answer;
                }
            }

            // Update inspection status
            $inspection->update(['status' => 'in_review']);

            return response()->json([
                'message' => 'AI analysis complete',
                'answers' => $answers,
                'total_questions' => $questions->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('AI inspection failed: ' . $e->getMessage());
            return response()->json(['error' => 'AI inspection failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Analyze a single question using Gemini AI.
     */
    private function analyzeQuestion(
        BoatCheck $question,
        array $images,
        array $specs,
        Yacht $yacht,
        string $apiKey
    ): array {
        $evidenceSources = $question->evidence_sources ?? ['photos'];

        // Build the prompt
        $prompt = $this->buildPrompt($question, $images, $specs, $evidenceSources);

        // Call Gemini API via HTTP (using Guzzle)
        $client = new \GuzzleHttp\Client();

        // Build content parts
        $parts = [['text' => $prompt]];

        // If evidence includes photos, add image descriptions
        // (We send text descriptions of images since we can't send actual images via REST easily)
        if (in_array('photos', $evidenceSources) && !empty($images)) {
            $imageList = "Available boat images:\n";
            foreach ($images as $img) {
                $imageList .= "- Image ID: {$img['id']}, Category: {$img['category']}, Path: {$img['url']}\n";
            }
            $parts[0]['text'] .= "\n\n" . $imageList;
        }

        $response = $client->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}",
            [
                'json' => [
                    'contents' => [
                        ['parts' => $parts]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'answer' => ['type' => 'STRING', 'description' => 'The answer to the inspection question'],
                                'confidence' => ['type' => 'NUMBER', 'description' => 'Confidence score from 0.0 to 1.0'],
                                'evidence_image_category' => ['type' => 'STRING', 'description' => 'Category of the photo used as evidence (e.g. Interior, Exterior)'],
                                'reasoning' => ['type' => 'STRING', 'description' => 'Brief explanation of why this answer was chosen'],
                            ],
                            'required' => ['answer', 'confidence', 'reasoning'],
                        ],
                    ],
                ],
                'timeout' => 60,
            ]
        );

        $body = json_decode($response->getBody()->getContents(), true);
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $parsed = json_decode($text, true) ?? [];

        // Find matching evidence image
        $evidenceImage = null;
        $evidenceCategory = $parsed['evidence_image_category'] ?? null;
        if ($evidenceCategory && !empty($images)) {
            foreach ($images as $img) {
                if (stripos($img['category'], $evidenceCategory) !== false) {
                    $evidenceImage = $img;
                    break;
                }
            }
            // Fallback to first image
            if (!$evidenceImage) {
                $evidenceImage = $images[0];
            }
        }

        return [
            'answer' => $parsed['answer'] ?? 'Unable to determine',
            'confidence' => min(1.0, max(0.0, floatval($parsed['confidence'] ?? 0.5))),
            'evidence' => [
                'image' => $evidenceImage,
                'reasoning' => $parsed['reasoning'] ?? '',
                'category' => $evidenceCategory,
            ],
        ];
    }

    /**
     * Build Gemini prompt for a specific question.
     */
    private function buildPrompt(BoatCheck $question, array $images, array $specs, array $evidenceSources): string
    {
        $prompt = "You are a professional marine vessel inspector. ";
        $prompt .= "Analyze the following boat and answer the inspection question.\n\n";

        // Add specs if needed
        if (in_array('spec_json', $evidenceSources) && !empty($specs)) {
            $prompt .= "## Boat Technical Specifications\n";
            $prompt .= json_encode($specs, JSON_PRETTY_PRINT) . "\n\n";
        }

        // Question
        $prompt .= "## Inspection Question\n";
        $prompt .= "Question: {$question->question_text}\n";
        $prompt .= "Answer Type: {$question->type}\n";

        if ($question->type === 'MULTI' && $question->options) {
            $prompt .= "Possible answers: " . implode(', ', $question->options) . "\n";
        }
        if ($question->type === 'YES_NO') {
            $prompt .= "Answer must be exactly 'YES' or 'NO'\n";
        }

        // Custom AI prompt
        if ($question->ai_prompt) {
            $prompt .= "\nSpecific instructions: {$question->ai_prompt}\n";
        }

        $prompt .= "\nProvide your answer with a confidence score (0.0 to 1.0) and identify which image category you would use as evidence.";

        return $prompt;
    }
}
