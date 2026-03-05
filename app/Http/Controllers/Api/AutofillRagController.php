<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutofillRagController extends Controller
{
    /**
     * The main RAG endpoint for the frontend.
     * Takes text input and an array of base64 images, searches Pinecone, 
     * and uses Gemini to generate a high-confidence JSON schema of boat specs.
     */
    public function autofill(Request $request)
    {
        $request->validate([
            'text_input' => 'nullable|string',
            'images' => 'nullable|array', // base64 images
        ]);

        $textInput = $request->input('text_input', '');
        $images = $request->input('images', []);

        if (empty($textInput) && empty($images)) {
            return response()->json(['error' => 'No input provided'], 400);
        }

        try {
            // STEP 1: Vision Extraction (Identify from user photos)
            $visionText = '';
            if (!empty($images)) {
                $visionText = "User uploaded " . count($images) . " images.";
                // In a full implementation we would call Gemini Vision here to extract text/logos.
                // $visionText = $this->extractVisionText($images); 
            }

            // STEP 2: Vector Search against Pinecone
            $searchQuery = trim($textInput . "\n" . $visionText);
            $pineconeMatches = $this->searchPinecone($searchQuery);
            
            if (empty($pineconeMatches)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No relevant boats found in catalog to build a consensus from.',
                    'consensus' => null
                ]);
            }

            // STEP 3: Consensus Generation
            $consensus = $this->generateConsensus($searchQuery, $pineconeMatches);

            return response()->json([
                'success' => true,
                'consensus' => $consensus,
                'pinecone_matches' => $pineconeMatches
            ]);

        } catch (\Exception $e) {
            Log::error("RAG Autofill Error: " . $e->getMessage());
            return response()->json(['error' => 'RAG Processing Failed: ' . $e->getMessage()], 500);
        }
    }

    private function searchPinecone(string $searchQuery): array
    {
        $openAiKey = env('OPENAI_API_KEY');
        $pineconeKey = env('PINECONE_API_KEY');
        $pineconeHost = env('PINECONE_HOST');

        if (!$openAiKey || !$pineconeKey || !$pineconeHost) {
            Log::warning("Missing API keys for Pinecone RAG.");
            return [];
        }

        // Embed the user's query
        $embedResponse = Http::withToken($openAiKey)->post('https://api.openai.com/v1/embeddings', [
            'model' => 'text-embedding-3-small',
            'input' => $searchQuery,
            'dimensions' => 1408
        ]);

        if (!$embedResponse->successful()) {
            throw new \Exception("Failed generating embedding for search query.");
        }

        $vector = $embedResponse->json('data.0.embedding');

        $url = str_starts_with($pineconeHost, 'http') ? "{$pineconeHost}/query" : "https://{$pineconeHost}/query";

        $pineconeResponse = Http::withHeaders([
            'Api-Key' => $pineconeKey,
            'Content-Type' => 'application/json'
        ])->post($url, [
            'vector' => $vector,
            'topK' => 10,
            'includeMetadata' => true
        ]);

        if (!$pineconeResponse->successful()) {
            throw new \Exception("Pinecone search failed.");
        }

        return $pineconeResponse->json('matches') ?? [];
    }

    private function generateConsensus(string $userInput, array $pineconeMatches): ?array
    {
        // Format the context for the LLM
        $contextString = "Here are the top 10 most similar known boats from our catalog:\n\n";
        foreach ($pineconeMatches as $i => $match) {
            $score = round(($match['score'] ?? 0) * 100);
            $meta = $match['metadata'] ?? [];
            $contextString .= "Match " . ($i+1) . " (Similarity: {$score}%):\n";
            $contextString .= json_encode($meta, JSON_PRETTY_PRINT) . "\n\n";
        }

        $systemPrompt = "You are a marine data consensus AI. The user provided some input text and/or images about a boat: '{$userInput}'.
I have retrieved the top 10 most mathematically similar structured boats from our Pinecone vector database.
Your job is to act as a consensus algorithm. Compare the user's input with the provided catalog matches.
Determine what the most likely TRUE specs are for the user's boat.

You MUST respond strictly in valid JSON format matching this structure:
{
    \"brand_id\": 123,
    \"brand_name\": \"String Name\",
    \"model_name\": \"String Name\",
    \"type_name\": \"String Type\",
    \"year\": 2012,
    \"length\": 11.48,
    \"confidence_score\": 95, // Integer 0-100 indicating how sure you are
    \"reasoning\": \"Short explanation of why you picked these fields.\"
}
If a field is completely unknown and no consensus can be made, return null for it.";

        $geminiKey = env('GEMINI_API_KEY');
        if (!$geminiKey) return null;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemPrompt . "\n\nContext Data:\n" . $contextString]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2 // Low temp for more deterministic consensus
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $geminiKey, $payload);

        if ($response->successful()) {
            $text = $response->json('candidates.0.content.parts.0.text');
            if ($text) {
                // Strip markdown backticks if present
                $text = str_replace(['```json', '```'], '', $text);
                return json_decode(trim($text), true);
            }
        }

        return null;
    }
}
