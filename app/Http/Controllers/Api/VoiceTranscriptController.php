<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\CallSessionTranscript;
use App\Models\Conversation;
use Illuminate\Http\Request;

class VoiceTranscriptController extends Controller
{
    public function store(Request $request)
    {
        $payload = $request->validate([
            'call_control_id' => 'nullable|string|max:120',
            'call_session_id' => 'nullable|string|max:120',
            'conversation_id' => 'nullable|string|max:120',
            'harbor_id' => 'nullable|integer',
            'language' => 'nullable|string|max:5',
            'latency' => 'nullable|array',
            'latency.first_token_ms' => 'nullable|integer|min:0',
            'latency.first_audio_ms' => 'nullable|integer|min:0',
            'latency.total_ms' => 'nullable|integer|min:0',
            'segments' => 'nullable|array',
            'segments.*.speaker' => 'nullable|string|max:20',
            'segments.*.text' => 'required_with:segments|string',
            'segments.*.sequence' => 'nullable|integer|min:0',
            'segments.*.started_at' => 'nullable|date',
            'segments.*.ended_at' => 'nullable|date',
            'segments.*.is_final' => 'nullable|boolean',
            'segments.*.metadata' => 'nullable|array',
            'transcript_text' => 'nullable|string',
        ]);

        $callSessionId = $payload['call_session_id'] ?? null;
        $callControlId = $payload['call_control_id'] ?? null;

        if (! $callSessionId && ! $callControlId) {
            return response()->json(['message' => 'call_control_id or call_session_id required'], 422);
        }

        $session = $callSessionId ? CallSession::find($callSessionId) : null;
        if (! $session && $callControlId) {
            $session = CallSession::where('call_control_id', $callControlId)->first();
        }

        if (! $session) {
            $session = CallSession::create([
                'call_control_id' => $callControlId,
                'direction' => 'unknown',
                'status' => 'streaming',
                'harbor_id' => $payload['harbor_id'] ?? null,
                'conversation_id' => $payload['conversation_id'] ?? null,
            ]);
        }

        if (! empty($payload['conversation_id']) && ! $session->conversation_id) {
            $session->conversation_id = $payload['conversation_id'];
        }
        if (! empty($payload['harbor_id']) && ! $session->harbor_id) {
            $session->harbor_id = (int) $payload['harbor_id'];
        }
        if (! empty($payload['language'])) {
            $session->language = $payload['language'];
        }
        if (! empty($payload['latency'])) {
            $latency = $payload['latency'];
            $session->latency_first_token_ms = $latency['first_token_ms'] ?? $session->latency_first_token_ms;
            $session->latency_first_audio_ms = $latency['first_audio_ms'] ?? $session->latency_first_audio_ms;
            $session->latency_total_ms = $latency['total_ms'] ?? $session->latency_total_ms;
        }

        $transcriptAppend = [];
        foreach ($payload['segments'] ?? [] as $segment) {
            $sequence = $segment['sequence'] ?? null;
            if ($sequence !== null) {
                $exists = CallSessionTranscript::where('call_session_id', $session->id)
                    ->where('sequence', $sequence)
                    ->exists();
                if ($exists) {
                    continue;
                }
            }

            CallSessionTranscript::create([
                'call_session_id' => $session->id,
                'conversation_id' => $session->conversation_id,
                'speaker' => $segment['speaker'] ?? 'unknown',
                'text' => $segment['text'],
                'sequence' => $sequence,
                'is_final' => $segment['is_final'] ?? true,
                'started_at' => $segment['started_at'] ?? null,
                'ended_at' => $segment['ended_at'] ?? null,
                'metadata' => $segment['metadata'] ?? null,
            ]);

            $transcriptAppend[] = strtoupper((string) ($segment['speaker'] ?? 'unknown')).': '.$segment['text'];
        }

        if (! empty($payload['transcript_text'])) {
            $session->transcript_text = $payload['transcript_text'];
        } elseif (! empty($transcriptAppend)) {
            $existing = $session->transcript_text ? trim((string) $session->transcript_text) : '';
            $addition = implode("\n", $transcriptAppend);
            $session->transcript_text = trim($existing === '' ? $addition : $existing."\n".$addition);
        }

        $session->save();

        if ($session->conversation_id) {
            $conversation = Conversation::find($session->conversation_id);
            if ($conversation) {
                $conversation->last_call_at = now();
                $conversation->save();
            }
        }

        return response()->json([
            'message' => 'ok',
            'call_session_id' => $session->id,
        ], 200);
    }
}
