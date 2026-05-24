<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HelpdeskController extends Controller
{
    public function voiceSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'language' => 'sometimes|string|in:nl,en,de,fr',
        ]);

        $vonageCallId = $this->initiateVonageCall($validated['phone_number']);
        $openaiSessionId = $this->createOpenAiRealtimeSession($validated['language'] ?? 'nl');

        $session = HelpdeskSession::create([
            'user_id' => $request->user()?->id,
            'channel' => 'phone',
            'phone_number' => $validated['phone_number'],
            'language' => $validated['language'] ?? 'nl',
            'vonage_call_id' => $vonageCallId,
            'openai_session_id' => $openaiSessionId,
            'status' => 'initiated',
        ]);

        return response()->json([
            'session_id' => 'vs_' . $session->id,
            'vonage_call_id' => $vonageCallId,
            'openai_session_id' => $openaiSessionId,
            'status' => 'initiated',
        ]);
    }

    public function recordEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
            'event_type' => 'required|string|max:100',
            'payload' => 'nullable|array',
        ]);

        $id = (int) str_replace('vs_', '', $validated['session_id']);
        $session = HelpdeskSession::findOrFail($id);

        $events = $session->events ?? [];
        $events[] = [
            'type' => $validated['event_type'],
            'payload' => $validated['payload'] ?? [],
            'recorded_at' => now()->toIso8601String(),
        ];
        $session->update(['events' => $events]);

        return response()->json(['message' => 'Event recorded.']);
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = HelpdeskSession::when($request->user()?->id, fn ($q, $id) => $q->where('user_id', $id))
            ->latest()
            ->limit(20)
            ->get();

        return response()->json(['data' => $sessions]);
    }

    public function transcript(Request $request, int $id): JsonResponse
    {
        $session = HelpdeskSession::findOrFail($id);
        return response()->json([
            'session_id' => 'vs_' . $session->id,
            'transcript' => $session->transcript,
            'events' => $session->events,
        ]);
    }

    private function initiateVonageCall(string $phoneNumber): string
    {
        $vonageKey = config('services.vonage.api_key');
        $vonageSecret = config('services.vonage.api_secret');
        $fromNumber = config('services.vonage.from_number', 'Schepenkring');

        if (!$vonageKey) {
            return 'ca_mock_' . Str::random(8);
        }

        try {
            $response = Http::withBasicAuth($vonageKey, $vonageSecret)
                ->post('https://api.nexmo.com/v1/calls', [
                    'to' => [['type' => 'phone', 'number' => ltrim($phoneNumber, '+')]],
                    'from' => ['type' => 'phone', 'number' => $fromNumber],
                    'ncco' => [['action' => 'talk', 'text' => 'Welkom bij Schepenkring helpdesk.']],
                ]);

            return $response->json('uuid', 'ca_' . Str::random(8));
        } catch (\Throwable $e) {
            Log::warning('HelpdeskController: Vonage call failed', ['error' => $e->getMessage()]);
            return 'ca_mock_' . Str::random(8);
        }
    }

    private function createOpenAiRealtimeSession(string $language): string
    {
        $apiKey = config('services.openai.api_key');

        if (!$apiKey) {
            return 'sess_mock_' . Str::random(8);
        }

        try {
            $response = Http::withToken($apiKey)
                ->post('https://api.openai.com/v1/realtime/sessions', [
                    'model' => 'gpt-4o-realtime-preview',
                    'voice' => 'alloy',
                    'instructions' => "You are a helpful boat marketplace assistant for Schepenkring. Respond in {$language}.",
                ]);

            return $response->json('id', 'sess_' . Str::random(8));
        } catch (\Throwable $e) {
            Log::warning('HelpdeskController: OpenAI Realtime session failed', ['error' => $e->getMessage()]);
            return 'sess_mock_' . Str::random(8);
        }
    }
}
