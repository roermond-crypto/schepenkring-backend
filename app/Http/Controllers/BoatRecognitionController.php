<?php

namespace App\Http\Controllers;

use App\Models\ImageEmbedding;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BoatRecognitionController extends Controller
{
    /**
     * Similarity threshold — above this we consider it a match.
     * 0.80 = 80% confident. Adjust if needed.
     */
    private const SIMILARITY_THRESHOLD = 0.80;

    /**
     * POST /api/yachts/recognize-boat
     *
     * Upload a photo of a boat → system checks if it matches any historical yacht.
     * Returns the matched yacht data if found, so the form can be auto-filled.
     */
    public function recognize(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
        ]);

        try {
            // 1. Save the uploaded image temporarily
            $tempPath = $request->file('image')->store('temp_recognition', 'public');
            $fullPath = storage_path('app/public/' . $tempPath);

            // 2. Generate embedding for the uploaded image
            $embeddingData = $this->generateEmbeddingFromImage($fullPath);

            // 3. Clean up temp file
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            Storage::disk('public')->delete($tempPath);

            if (isset($embeddingData['error'])) {
                return response()->json([
                    'matched' => false,
                    'message' => 'Could not analyze image: ' . $embeddingData['error'],
                ], 200);
            }

            $queryEmbedding = $embeddingData['embedding'];

            // 4. Load all yacht embeddings from DB
            $storedEmbeddings = ImageEmbedding::whereNotNull('yacht_id')
                ->whereNotNull('embedding')
                ->with(['yacht' => function ($q) {
                    $q->with(['images', 'availabilityRules']);
                }])
                ->get();

            if ($storedEmbeddings->isEmpty()) {
                return response()->json([
                    'matched' => false,
                    'message' => 'No historical boats indexed yet. This is the first listing!',
                    'suggestions' => [],
                ], 200);
            }

            // 5. Compute cosine similarity against all stored embeddings
            $matches = [];
            foreach ($storedEmbeddings as $stored) {
                if (!$stored->yacht || !$stored->embedding) {
                    continue;
                }

                $score = $this->cosineSimilarity($queryEmbedding, $stored->embedding);

                if ($score >= self::SIMILARITY_THRESHOLD) {
                    $matches[] = [
                        'score' => round($score, 4),
                        'yacht_id' => $stored->yacht_id,
                        'yacht' => $stored->yacht,
                        'embedding_description' => $stored->description,
                    ];
                }
            }

            // Sort by score descending
            usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

            if (empty($matches)) {
                return response()->json([
                    'matched' => false,
                    'message' => 'No matching boat found in fleet history.',
                    'ai_description' => $embeddingData['description'] ?? null,
                    'suggestions' => [],
                ], 200);
            }

            // Return the best match
            $bestMatch = $matches[0];

            return response()->json([
                'matched' => true,
                'score' => $bestMatch['score'],
                'yacht' => $bestMatch['yacht'],
                'ai_description' => $embeddingData['description'] ?? null,
                'message' => 'Boat recognized! This looks like "' . ($bestMatch['yacht']->boat_name ?? 'Unknown') . '"',
                'other_matches' => array_slice($matches, 1, 4), // up to 4 other suggestions
            ], 200);

        } catch (\Exception $e) {
            Log::error('Boat recognition failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'matched' => false,
                'message' => 'Recognition service error: ' . $e->getMessage(),
            ], 200); // Return 200 so the form still works
        }
    }

    /**
     * POST /api/yachts/{id}/generate-embedding
     *
     * Generate and store an embedding for a yacht's main image.
     * Called manually or automatically after yacht creation.
     */
    public function generateEmbedding(Request $request, $id): JsonResponse
    {
        try {
            $yacht = Yacht::findOrFail($id);

            if (!$yacht->main_image) {
                return response()->json([
                    'message' => 'Yacht has no main image to generate embedding from.',
                ], 422);
            }

            $imagePath = storage_path('app/public/' . $yacht->main_image);

            if (!file_exists($imagePath)) {
                return response()->json([
                    'message' => 'Main image file not found on disk.',
                ], 404);
            }

            $embeddingData = $this->generateEmbeddingFromImage($imagePath);

            if (isset($embeddingData['error'])) {
                return response()->json([
                    'message' => 'Embedding generation failed: ' . $embeddingData['error'],
                ], 500);
            }

            // Store or update the embedding
            $embedding = ImageEmbedding::updateOrCreate(
                [
                    'yacht_id' => $yacht->id,
                    'is_main_image' => true,
                ],
                [
                    'filename' => basename($yacht->main_image),
                    'public_url' => $yacht->main_image,
                    'embedding' => $embeddingData['embedding'],
                    'description' => $embeddingData['description'] ?? null,
                ]
            );

            return response()->json([
                'message' => 'Embedding generated successfully for "' . $yacht->boat_name . '"',
                'embedding_id' => $embedding->id,
                'description' => $embedding->description,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Generate embedding failed: ' . $e->getMessage(), [
                'yacht_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate embedding: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate an embedding for a yacht (called internally, e.g., after save).
     */
    public function generateEmbeddingForYacht(Yacht $yacht): void
    {
        if (!$yacht->main_image) {
            return;
        }

        $imagePath = storage_path('app/public/' . $yacht->main_image);

        if (!file_exists($imagePath)) {
            Log::warning("Cannot generate embedding: image not found at {$imagePath}");
            return;
        }

        try {
            $embeddingData = $this->generateEmbeddingFromImage($imagePath);

            if (isset($embeddingData['error'])) {
                Log::error('Auto-embedding failed for yacht ' . $yacht->id . ': ' . $embeddingData['error']);
                return;
            }

            ImageEmbedding::updateOrCreate(
                [
                    'yacht_id' => $yacht->id,
                    'is_main_image' => true,
                ],
                [
                    'filename' => basename($yacht->main_image),
                    'public_url' => $yacht->main_image,
                    'embedding' => $embeddingData['embedding'],
                    'description' => $embeddingData['description'] ?? null,
                ]
            );

            Log::info("Embedding auto-generated for yacht #{$yacht->id} ({$yacht->boat_name})");

        } catch (\Exception $e) {
            Log::error("Auto-embedding exception for yacht {$yacht->id}: " . $e->getMessage());
        }
    }

    /**
     * Run the Python script to generate an embedding from an image file.
     *
     * @return array{embedding?: array, description?: string, error?: string}
     */
    private function generateEmbeddingFromImage(string $imagePath): array
    {
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            return ['error' => 'GEMINI_API_KEY not configured'];
        }

        // Determine Python path
        if (PHP_OS_FAMILY === 'Windows') {
            $pythonPath = 'python';
        } else {
            $venvPath = base_path('venv/bin/python');
            $pythonPath = (file_exists($venvPath) && is_executable($venvPath))
                ? $venvPath
                : 'python3';
        }

        $scriptPath = app_path('Scripts/generate_embedding.py');

        Log::info('Running generate_embedding.py', [
            'python' => $pythonPath,
            'script' => $scriptPath,
            'image' => $imagePath,
        ]);

        $process = new Process([
            $pythonPath,
            $scriptPath,
            $apiKey,
            $imagePath,
        ]);

        $process->setTimeout(120); // 2 minutes

        try {
            $process->mustRun();
            $output = $process->getOutput();
            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON from generate_embedding.py', ['output' => $output]);
                return ['error' => 'Invalid response from embedding service'];
            }

            return $data;

        } catch (ProcessFailedException $e) {
            Log::error('generate_embedding.py failed', [
                'error' => $e->getMessage(),
                'stderr' => $process->getErrorOutput(),
            ]);
            return ['error' => 'Embedding process failed: ' . $process->getErrorOutput()];
        }
    }

    /**
     * Compute cosine similarity between two vectors.
     * Returns a value between -1 and 1 (1 = identical).
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }
}
