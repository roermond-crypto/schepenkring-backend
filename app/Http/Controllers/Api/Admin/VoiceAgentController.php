<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VoiceAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceAgentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => VoiceAgent::orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $agent = VoiceAgent::create($validated);

        return response()->json(['data' => $agent], 201);
    }

    public function update(Request $request, VoiceAgent $agent): JsonResponse
    {
        $validated = $this->validated($request, $agent->id);
        $agent->update($validated);

        return response()->json(['data' => $agent]);
    }

    public function destroy(VoiceAgent $agent): JsonResponse
    {
        $agent->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:voice_agents,slug'.($ignoreId ? ",{$ignoreId}" : ''),
            'language' => 'nullable|string|max:5',
            'purpose' => 'nullable|string',
            'retell_agent_id' => 'nullable|string|max:255',
            'voice' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'prompt' => 'nullable|string',
            'calling_hours' => 'nullable|array',
            'retry_rules' => 'nullable|array',
            'spend_cap_eur' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:active,inactive',
            'knowledge_categories' => 'nullable|array',
        ]);
    }
}
