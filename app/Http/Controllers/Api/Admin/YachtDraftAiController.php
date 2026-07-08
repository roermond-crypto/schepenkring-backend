<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Yacht;
use App\Models\YachtDraft;
use App\Services\PineconeMatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YachtDraftAiController extends Controller
{
    // Confidence tiers applied to PineconeMatcherService::matchAndBuildConsensus()'s
    // per-field confidence output. That service only ever returns a field when it
    // found a real majority-agreement or a high-similarity top match (>= 0.75), so
    // "low confidence" already means the field is simply absent — never suggested,
    // never blindly saved. This just splits the remaining range into an
    // auto-fill-eligible tier and a confirm-before-applying tier.
    private const HIGH_CONFIDENCE_THRESHOLD = 0.85;

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
        $draftPayload = $draft->payload_json ?? [];

        // A manually-selected reference boat (via select-reference-boat) is
        // optional context, not a requirement — retrieval runs automatically
        // against the draft's own current field values either way, same as
        // aiMatches(). If one was selected, fold its identifying fields into
        // the query so it biases retrieval without gating the whole feature
        // on a manual step.
        $referenceId = $draft->ai_state_json['reference_yacht_id'] ?? null;
        $queryPayload = $draftPayload;
        if ($referenceId && ($reference = Yacht::find($referenceId))) {
            $queryPayload = array_merge(
                array_filter($reference->only(['boat_name', 'manufacturer', 'model', 'boat_type'])),
                $draftPayload,
            );
        }

        try {
            $match = $this->pinecone->matchAndBuildConsensus($queryPayload);
        } catch (\Throwable $e) {
            Log::error('YachtDraftAiController: autofill retrieval failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'AI autofill temporarily unavailable.'], 502);
        }

        $consensusValues = $match['consensus_values'] ?? [];
        $fieldConfidence = $match['field_confidence'] ?? [];
        $fieldSources = $match['field_sources'] ?? [];

        // Never suggest a field the draft already has a value for, and never
        // suggest a field with no confidence score at all — matchAndBuildConsensus
        // only emits a field when it found real agreement across similar boats,
        // so an absent field already means "too low confidence, leave empty".
        $tieredFields = [];
        foreach ($consensusValues as $field => $value) {
            if (array_key_exists($field, $draftPayload) && $draftPayload[$field] !== null && $draftPayload[$field] !== '') {
                continue;
            }
            $confidence = $fieldConfidence[$field] ?? null;
            if ($confidence === null) {
                continue;
            }
            $tieredFields[$field] = [
                'value' => $value,
                'confidence' => $confidence,
                'tier' => $confidence >= self::HIGH_CONFIDENCE_THRESHOLD ? 'high' : 'medium',
                'source' => $fieldSources[$field] ?? 'pinecone_consensus',
            ];
        }

        $overallConfidence = empty($tieredFields)
            ? 0.0
            : round(array_sum(array_column($tieredFields, 'confidence')) / count($tieredFields), 2);

        $sourceBoats = collect($match['top_matches'] ?? [])
            ->map(fn (array $m) => [
                'yacht_id' => $m['boat']['id'] ?? null,
                'boat_name' => $m['boat']['boat_name'] ?? null,
                'source_url' => $m['boat']['external_url'] ?? null,
                'score' => $m['score'] ?? null,
            ])
            ->values();

        $aiState = $draft->ai_state_json ?? [];
        $aiState['autofill_suggestions'] = [
            'status' => 'pending_review',
            'reference_yacht_id' => $referenceId,
            'confidence' => $overallConfidence,
            'fields' => array_map(fn ($f) => $f['value'], $tieredFields),
            'field_tiers' => $tieredFields,
            'source_log' => [
                'source' => 'verified_schepenkring_ai_library',
                'reference_yacht_id' => $referenceId,
                'source_boats' => $sourceBoats,
                'generated_at' => now()->toISOString(),
            ],
        ];
        $draft->ai_state_json = $aiState;
        $draft->save();

        AuditLog::create([
            'action' => 'ai_autofill_generated',
            'risk_level' => 'low',
            'result' => 'success',
            'actor_id' => $request->user()?->id,
            'entity_type' => 'yacht_draft',
            'entity_id' => $draft->id,
            'meta' => [
                'draft_id' => $draftId,
                'suggested_fields' => array_keys($tieredFields),
                'high_confidence_fields' => array_keys(array_filter($tieredFields, fn ($f) => $f['tier'] === 'high')),
                'medium_confidence_fields' => array_keys(array_filter($tieredFields, fn ($f) => $f['tier'] === 'medium')),
                'overall_confidence' => $overallConfidence,
                'source_boats' => $sourceBoats,
            ],
        ]);

        return response()->json([
            'suggested_fields' => array_keys($tieredFields),
            'field_tiers' => $tieredFields,
            'confidence' => $overallConfidence,
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

        AuditLog::create([
            'action' => 'ai_autofill_approved',
            'risk_level' => 'low',
            'result' => 'success',
            'actor_id' => $request->user()?->id,
            'entity_type' => 'yacht_draft',
            'entity_id' => $draft->id,
            'meta' => [
                'draft_id' => $draftId,
                'approved_fields' => array_keys($approved),
                'rejected_fields' => array_keys(array_diff_key($suggestions, $approved)),
            ],
        ]);

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
}
