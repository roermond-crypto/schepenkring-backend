<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ImageEmbedding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BoatSearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'nullable|string|max:255',  // allow empty to list all
        ]);

        $query = $request->input('query', '');

        try {
            // If query is empty, return all boats sorted by newest first
            if (empty(trim($query))) {
                $embeddings = ImageEmbedding::latest()->get();
                $matches = $embeddings->map(function ($item) {
                    return [
                        'id'       => $item->filename,
                        'score'    => null,  // no relevance score for empty query
                        'metadata' => [
                            'url'         => $item->public_url,
                            'description' => $item->description,
                        ],
                    ];
                });

                return response()->json([
                    'status'  => 'success',
                    'results' => ['matches' => $matches],
                ]);
            }

            // 1. Generate embedding for the query text
            $queryEmbedding = $this->getEmbedding($query);

            // 2. Fetch all stored embeddings from the database
            $allEmbeddings = ImageEmbedding::all();

            // 3. Compute cosine similarity for each
            $matches = [];
            foreach ($allEmbeddings as $item) {
                $storedEmbedding = $item->embedding; // already an array from JSON cast
                $similarity = $this->cosineSimilarity($queryEmbedding, $storedEmbedding);

                $matches[] = [
                    'id'       => $item->filename,
                    'score'    => $similarity,
                    'metadata' => [
                        'url'         => $item->public_url,
                        'description' => $item->description,
                    ],
                ];
            }

            // 4. Sort by similarity (highest first) and take top 20
            usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
            $matches = array_slice($matches, 0, 20);

            return response()->json([
                'status'  => 'success',
                'results' => ['matches' => $matches],
            ]);

        } catch (\Exception $e) {
            Log::error('Boat search failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Search failed',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate an embedding for a given text using Gemini API.
     *
     * @param string $text
     * @return array
     * @throws \Exception
     */
    private function getEmbedding(string $text): array
    {
        $apiKey = env('GEMINI_API_KEY');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key=" . $apiKey;

        $response = Http::post($url, [
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ]
        ]);

        if (!$response->successful()) {
            throw new \Exception('Embedding API error: ' . $response->body());
        }

        $data = $response->json();
        return $data['embedding']['values'] ?? throw new \Exception('Embedding not found in response');
    }

    /**
     * Compute cosine similarity between two vectors.
     *
     * @param array $a
     * @param array $b
     * @return float
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}