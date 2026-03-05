<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Yacht;
use App\Models\Faq; // This is the model causing the crash
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeminiController extends Controller
{
    public function chat(Request $request)
    {
        // --- START VOICE INTEGRATION ---
        $user = auth()->user();
        $voiceId = $user ? ($user->ai_voice_id ?? 'voice_4') : 'voice_4';

        $voiceProfiles = [
            'voice_1' => "feminine, warm, and motherly. Use soft emojis 🌸.",
            'voice_2' => "masculine, professional, and direct. No emojis.",
            'voice_3' => "energetic, young, and bubbly. Use many emojis ✨.",
            'voice_4' => "calm, highly sophisticated, and luxury-focused. Use formal language.",
            'voice_5' => "funny, casual, and a bit cheeky with maritime jokes ⚓.",
        ];

        $chosenProfile = $voiceProfiles[$voiceId] ?? $voiceProfiles['voice_4'];

        // 1. API CONFIGURATION
        $apiKey = "AIzaSyAQL886bhoR8gpfX6X7R324mLZX4U6i1pU";
        $model = "gemini-2.5-flash-lite"; 

        // 2. FETCH KNOWLEDGE BASE (With Crash Protection)
        $yachtData = "No local yacht data available.";
        $faqData = "No local Faq data available.";

        // Protect against missing Yachts table
        try {
            $yachtData = Yacht::where('status', '!=', 'Draft')->get()->map(function($y) {
                return "Vessel: {$y->name} | Price: €{$y->price} | Make: {$y->make}";
            })->join("\n") ?: "No yachts currently listed.";
        } catch (\Exception $e) {
            Log::warning("Yacht table not found, skipping local fleet data.");
        }

        // Protect against missing Faqs table (The fix for your 500 error)
        try {
            $faqData = Faq::all()->map(function($f) {
                return "Q: {$f->question}\nA: {$f->answer}";
            })->join("\n\n") ?: "No local Faqs found.";
        } catch (\Exception $e) {
            Log::warning("Faq table not found, relying on trained data.");
        }

        // 3. SYSTEM CONTEXT
        $systemContext = "
        ROLE: You are the 'Kring Schepen Yachts' AI Concierge. You are a premium maritime consultant.
        TONE & PERSONALITY: You must sound $chosenProfile

        IMPORTANT: If local fleet or Faq data is empty, do NOT mention an error. 
        Instead, use your internal trained knowledge to provide expert advice on luxury yachts, 
        boat buying, and the yachting lifestyle.

        LOCAL DATA (Absolute Truth if provided):
        ---
        CURRENT FLEET:
        $yachtData
        ---
        FaqS:
        $faqData
        ";

        // 4. PREPARE DATA FROM FRONTEND
        $userMessage = $request->input('message');
        $sessionId = $request->input('session_id', 'anon-' . uniqid());
        $clientHistory = json_decode($request->input('history', '[]'), true);

        // 5. FORMAT CONTENTS
        $formattedContents = [];
        foreach ($clientHistory as $msg) {
            $role = ($msg['role'] === 'user') ? 'user' : 'model';
            $formattedContents[] = ["role" => $role, "parts" => [["text" => $msg['content']]]];
        }
        $formattedContents[] = ["role" => "user", "parts" => [["text" => $userMessage]]];

        // 6. CALL API
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    "system_instruction" => ["parts" => [["text" => $systemContext]]],
                    "contents" => $formattedContents,
                    "generationConfig" => ["temperature" => 0.7, "maxOutputTokens" => 800]
                ]);

            if ($response->failed()) {
                Log::error("Gemini Error: " . $response->body());
                return response()->json(['reply' => "I'm having trouble connecting to my maritime intelligence. Please try again."]);
            }

            $aiResponseText = $response->json('candidates.0.content.parts.0.text') ?? "I am here to assist with your yachting needs.";

            // 7. SAVE HISTORY (Optional: Wrap in try-catch too)
            try {
                ChatMessage::create([
                    'session_id' => $sessionId,
                    'role'       => 'assistant',
                    'content'    => $aiResponseText,
                ]);
            } catch (\Exception $e) {}

            return response()->json([
                'reply' => $aiResponseText,
                'voice_meta' => ['voice_id' => $voiceId, 'profile' => $chosenProfile]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Concierge Offline: ' . $e->getMessage()], 500);
        }
    }
}