<?php

namespace App\Services;

use App\Models\Yacht;
use App\Models\BoatVideo;
use App\Models\BoatVideoSetting;
use Illuminate\Support\Facades\Http;

class AiCaptionGeneratorService
{
    /**
     * Generate Caption and Hashtags using OpenAI API.
     */
    public function generateForYacht(Yacht $yacht)
    {
        // Mocking the AI Prompt Construction. In a real scenario, this would call OpenAI.
        // For this task, we will simulate the OpenAI call to ensure the flow works.
        // We extract specs to construct the prompt.
        
        $specs = [
            'Name' => $yacht->boat_name,
            'Year' => $yacht->year,
            'Price' => $yacht->price,
            'Type' => $yacht->boat_type,
            'Beam' => $yacht->beam,
            'Draft' => $yacht->draft,
            'Engine' => $yacht->engine_manufacturer ?? 'Unknown',
            'Description' => $yacht->short_description_en ?? 'A beautiful vessel ready for the sea.',
        ];

        $prompt = "Write an engaging social media caption for a boat with the following specs: " . json_encode($specs);
        
        // We simulate the API call here, but use env variable if it exists.
        $apiKey = env('OPENAI_API_KEY');
        
        if ($apiKey) {
            $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert yacht broker social media manager. Provide a JSON response with keys: caption, hashtags, seo_text.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object']
            ]);

            if ($response->successful()) {
                $data = json_decode($response->json('choices')[0]['message']['content'], true);
                return [
                    'caption' => $data['caption'] ?? 'Check out this stunning yacht!',
                    'hashtags' => $data['hashtags'] ?? '#yacht #boating #forsale',
                    'seo_text' => $data['seo_text'] ?? 'Yacht for sale. ' . $yacht->boat_name,
                ];
            }
        }
        
        // Fallback or simulated response
        return [
            'caption' => "Discover the amazing {$yacht->boat_name}! Built in {$yacht->year}, this {$yacht->boat_type} is perfect for your next adventure. \n\nFeatures: {$specs['Engine']} engine, {$yacht->beam} beam.",
            'hashtags' => "#yachtlife #{$yacht->boat_name} #boating #yachtforsale",
            'seo_text' => "Buy {$yacht->boat_name}, a beautiful {$yacht->year} {$yacht->boat_type} with {$specs['Engine']} engine.",
        ];
    }
}
