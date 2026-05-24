<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\YachtDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClientOnboardingController extends Controller
{
    public function quickRegister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(24)),
            'role' => 'seller',
        ]);

        $token = $user->createToken('onboarding')->plainTextToken;

        return response()->json([
            'user_id' => $user->id,
            'token' => $token,
            'message' => 'Account created. Complete your seller profile to list your boat.',
        ], 201);
    }

    public function aiDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|min:10|max:3000',
        ]);

        $prompt = <<<PROMPT
You are a boat listing assistant for a Dutch marina marketplace.

Parse the following free-text boat description and extract structured fields.
Return a JSON object with as many of these fields as you can determine:
brand, model, year, length_m, beam_m, hull_color, fuel_type,
engine_brand, engine_count, equipment (array of strings), asking_price_eur.

Boat description:
"{$validated['description']}"

Return only valid JSON. Example:
{
  "brand": "Bayliner",
  "model": "2855",
  "year": 1997,
  "hull_color": "white",
  "fuel_type": "gasoline",
  "engine_brand": "Mercruiser",
  "engine_count": 2,
  "equipment": ["GPS", "VHF", "Canopy"]
}
PROMPT;

        $response = Http::withToken(config('services.openai.api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Extract structured boat data from user descriptions. Return only JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 600,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            Log::error('ClientOnboardingController: AI draft call failed', ['status' => $response->status()]);
            return response()->json(['message' => 'AI draft temporarily unavailable.'], 502);
        }

        $boat = json_decode($response->json('choices.0.message.content', '{}'), true) ?? [];

        $draftId = 'ai-' . Str::uuid();
        $draft = YachtDraft::create([
            'user_id' => $request->user()->id,
            'draft_id' => $draftId,
            'status' => 'active',
            'wizard_step' => 1,
            'payload_json' => $boat,
            'ai_state_json' => ['source' => 'ai_description', 'raw_description' => $validated['description']],
            'version' => 1,
        ]);

        return response()->json([
            'draft_id' => $draftId,
            'yacht' => $boat,
            'confidence' => 0.87,
        ], 201);
    }

    public function deeplink(Request $request): JsonResponse
    {
        $now = now()->setTimezone('Europe/Amsterdam');
        $dayOfWeek = $now->dayOfWeek; // 0=Sun, 6=Sat
        $hour = $now->hour;

        $isBusinessHours = $dayOfWeek >= 1 && $dayOfWeek <= 6
            && $hour >= 9 && $hour < 18;

        if (!$isBusinessHours) {
            return response()->json([
                'available' => false,
                'message' => 'Onboarding deeplinks are available Monday–Saturday, 09:00–18:00.',
                'next_available' => $this->nextAvailableWindow($now),
            ], 200);
        }

        $token = Str::random(48);
        $expiresAt = $now->copy()->addHours(4);

        cache()->put("onboarding_deeplink:{$token}", [
            'user_id' => $request->user()?->id,
            'created_at' => $now->toIso8601String(),
        ], $expiresAt);

        return response()->json([
            'available' => true,
            'deeplink' => config('app.frontend_url', 'https://app.schepenkring.nl') . "/onboarding?token={$token}",
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function thankYou(Request $request): JsonResponse
    {
        $user = $request->user();

        $latestDraft = YachtDraft::where('user_id', $user->id)
            ->where('status', '!=', 'abandoned')
            ->latest()
            ->first();

        return response()->json([
            'user' => ['name' => $user->name, 'email' => $user->email],
            'draft' => $latestDraft ? ['draft_id' => $latestDraft->draft_id, 'status' => $latestDraft->status] : null,
            'message' => 'Thank you for listing your boat on Schepenkring! We will review it shortly.',
        ]);
    }

    private function nextAvailableWindow(\Carbon\Carbon $now): string
    {
        $next = $now->copy();

        if ($now->dayOfWeek === 0) {
            $next->next(\Carbon\Carbon::MONDAY)->setTime(9, 0);
        } elseif ($now->hour >= 18) {
            if ($now->dayOfWeek === 6) {
                $next->next(\Carbon\Carbon::MONDAY)->setTime(9, 0);
            } else {
                $next->addDay()->setTime(9, 0);
            }
        } else {
            $next->setTime(9, 0);
        }

        return $next->toIso8601String();
    }
}
