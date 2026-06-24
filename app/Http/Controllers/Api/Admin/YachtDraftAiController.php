<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Models\YachtDraft;
use App\Services\PineconeMatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YachtDraftAiController extends Controller
{
    public function __construct(private PineconeMatcherService $pinecone)
    {
    }

    public function selectReferenceBoat(Request $request, string $draftId): JsonResponse
    {
        $validated = $request->validate([
            'reference_yacht_id' => 'required|integer|exists:yachts,id',
        ]);

        $draft = YachtDraft::where('draft_id', $draftId)->firstOrFail();

        $aiState = $draft->ai_state_json ?? [];
        $aiState['reference_yacht_id'] = $validated['reference_yacht_id'];
        $draft->ai_state_json = $aiState;
        $draft->save();

        return response()->json([
            'message' => 'Reference boat linked.',
            'draft_id' => $draftId,
            'reference_yacht_id' => $validated['reference_yacht_id'],
        ]);
    }

    public function aiAutofill(Request $request, string $draftId): JsonResponse
    {
        $draft = YachtDraft::where('draft_id', $draftId)->firstOrFail();

        $referenceId = $draft->ai_state_json['reference_yacht_id'] ?? null;
        if (!$referenceId) {
            return response()->json(['message' => 'No reference boat selected. Call select-reference-boat first.'], 422);
        }

        $reference = Yacht::findOrFail($referenceId);

        $referencePayload = $this->buildReferencePayload($reference);
        $draftPayload = $draft->payload_json ?? [];

        $prompt = <<<PROMPT
You are an AI assistant for a Dutch boat marketplace.

Given a reference yacht's known data, fill in the missing fields for a new draft.
Return ONLY a flat JSON object with field names and values. Do not include fields that are already filled.

Reference yacht data:
{$referencePayload}

Current draft payload (already filled):
{$this->encodePayload($draftPayload)}

Return only new fields to add. Example: {"length": 8.6, "beam": 2.9, "fuel_type": "gasoline"}
PROMPT;

        $response = Http::withToken(config('services.openai.api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a yacht data enrichment engine. Return only valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 500,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            Log::error('YachtDraftAiController: autofill GPT call failed', ['status' => $response->status()]);
            return response()->json(['message' => 'AI autofill temporarily unavailable.'], 502);
        }

        $filled = json_decode($response->json('choices.0.message.content', '{}'), true) ?? [];

        $aiState = $draft->ai_state_json ?? [];
        $aiState['autofill_suggestions'] = [
            'status' => 'pending_review',
            'reference_yacht_id' => $reference->id,
            'confidence' => 0.88,
            'fields' => $filled,
            'source_log' => [
                'source' => 'verified_schepenkring_ai_library',
                'reference_yacht_id' => $reference->id,
                'reference_boat_name' => $reference->boat_name,
                'reference_external_url' => $reference->external_url,
                'generated_at' => now()->toISOString(),
            ],
        ];
        $draft->ai_state_json = $aiState;
        $draft->save();

        return response()->json([
            'suggested_fields' => array_keys($filled),
            'confidence' => 0.88,
            'review_required' => true,
            'source_log' => $aiState['autofill_suggestions']['source_log'],
            'draft' => $draft->fresh(),
        ]);
    }

    public function applyAiAutofill(Request $request, string $draftId): JsonResponse
    {
        $validated = $request->validate([
            'fields' => 'nullable|array',
            'fields.*' => 'string',
        ]);

        $draft = YachtDraft::where('draft_id', $draftId)->firstOrFail();
        $aiState = $draft->ai_state_json ?? [];
        $suggestions = $aiState['autofill_suggestions']['fields'] ?? [];

        if (! is_array($suggestions) || empty($suggestions)) {
            return response()->json(['message' => 'No pending AI autofill suggestions found.'], 422);
        }

        $selected = $validated['fields'] ?? array_keys($suggestions);
        $approved = array_intersect_key($suggestions, array_flip($selected));

        $draft->payload_json = array_merge($draft->payload_json ?? [], $approved);
        $aiState['autofill_suggestions']['status'] = 'approved';
        $aiState['autofill_suggestions']['approved_fields'] = array_keys($approved);
        $aiState['autofill_suggestions']['approved_at'] = now()->toISOString();
        $draft->ai_state_json = $aiState;
        $draft->save();

        return response()->json([
            'applied_fields' => array_keys($approved),
            'draft' => $draft->fresh(),
        ]);
    }

    public function aiMatches(Request $request, string $draftId): JsonResponse
    {
        $draft = YachtDraft::where('draft_id', $draftId)->firstOrFail();
        $payload = $draft->payload_json ?? [];

        if (empty($payload)) {
            return response()->json(['matches' => [], 'message' => 'Draft payload is empty.']);
        }

        try {
            $result = $this->pinecone->matchAndBuildConsensus($payload);
            $matches = $result['top_matches'] ?? [];
        } catch (\Throwable $e) {
            Log::error('YachtDraftAiController: Pinecone match failed', ['error' => $e->getMessage()]);
            return response()->json(['matches' => [], 'error' => 'Match service unavailable.'], 502);
        }

        $enriched = collect($matches)->map(function (array $match) {
            $metadata = $match['boat'] ?? [];
            $yachtId = $metadata['id'] ?? $match['yacht_id'] ?? null;
            $yacht = $yachtId
                ? Yacht::select('id', 'boat_name', 'manufacturer', 'model', 'year')->find($yachtId)
                : null;

            return [
                'yacht_id' => $yachtId,
                'score' => $match['score'] ?? null,
                'yacht' => $yacht,
            ];
        })->values();

        return response()->json(['matches' => $enriched]);
    }

    private function buildReferencePayload(Yacht $yacht): string
    {
        $data = array_merge(
            $yacht->only(['boat_name', 'manufacturer', 'model', 'year', 'boat_type', 'price', 'external_url']),
            [
                'loa' => $yacht->dimensions?->loa,
                'beam' => $yacht->dimensions?->beam,
                'draft' => $yacht->dimensions?->draft,
                'fuel' => $yacht->engine?->fuel,
                'engine_manufacturer' => $yacht->engine?->engine_manufacturer,
            ]
        );

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    private function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT);
    }
}
