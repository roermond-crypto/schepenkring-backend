<?php

namespace App\Http\Controllers\Api;

use App\Models\UserVoiceSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CopilotVoiceSettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $settings = UserVoiceSetting::where('user_id', $user->id)->first();

        return response()->json($settings ?? [
            'user_id' => $user->id,
            'tts_enabled' => false,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'tts_voice_id' => 'nullable|string|max:120',
            'tts_enabled' => 'nullable|boolean',
            'stt_language' => 'nullable|string|max:10',
            'speaking_rate' => 'nullable|numeric|min:0.5|max:2.0',
            'pitch' => 'nullable|numeric|min:-2|max:2',
        ]);

        $settings = UserVoiceSetting::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json($settings);
    }
}
