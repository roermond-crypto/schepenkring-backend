<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Models\YachtDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YachtDraftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $drafts = YachtDraft::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $drafts]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'draft_id'             => 'required|string|max:120',
            'yacht_id'             => 'nullable|integer|exists:yachts,id',
            'status'               => 'nullable|string|in:active,submitted,abandoned',
            'wizard_step'          => 'nullable|integer|min:1|max:5',
            'payload_json'         => 'nullable|array',
            'ui_state_json'        => 'nullable|array',
            'images_manifest_json' => 'nullable|array',
            'ai_state_json'        => 'nullable|array',
            'version'              => 'nullable|integer|min:1',
            'client_saved_at'      => 'nullable|date',
        ]);

        $draft = YachtDraft::query()
            ->where('user_id', $user->id)
            ->where('draft_id', $validated['draft_id'])
            ->first();

        if ($draft && isset($validated['version']) && (int) $validated['version'] !== (int) $draft->version) {
            return response()->json([
                'message' => 'Draft version conflict.',
                'code' => 'version_conflict',
                'server' => $draft,
            ], 409);
        }

        if (!$draft) {
            $draft = new YachtDraft([
                'user_id' => $user->id,
                'draft_id' => $validated['draft_id'],
                'version' => 1,
            ]);
        } else {
            $draft->version = (int) $draft->version + 1;
        }

        if (array_key_exists('yacht_id', $validated) && $validated['yacht_id']) {
            $yacht = Yacht::findOrFail((int) $validated['yacht_id']);
            if (!$this->canAccessYacht($user, $yacht)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $draft->yacht_id = $yacht->id;
        }

        if (array_key_exists('status', $validated)) {
            $draft->status = $validated['status'] ?? 'active';
        }
        if (array_key_exists('wizard_step', $validated)) {
            $draft->wizard_step = (int) $validated['wizard_step'];
        }
        if (array_key_exists('payload_json', $validated)) {
            $draft->payload_json = $validated['payload_json'];
        }
        if (array_key_exists('ui_state_json', $validated)) {
            $draft->ui_state_json = $validated['ui_state_json'];
        }
        if (array_key_exists('images_manifest_json', $validated)) {
            $draft->images_manifest_json = $validated['images_manifest_json'];
        }
        if (array_key_exists('ai_state_json', $validated)) {
            $draft->ai_state_json = $validated['ai_state_json'];
        }
        if (array_key_exists('client_saved_at', $validated)) {
            $draft->last_client_saved_at = $validated['client_saved_at'];
        }

        $draft->save();

        return response()->json($draft, $draft->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, string $draftId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $draft = $this->findDraftOrFail($user->id, $draftId);

        return response()->json($draft);
    }

    public function update(Request $request, string $draftId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'version'                => 'required|integer|min:1',
            'status'                 => 'nullable|string|in:active,submitted,abandoned',
            'wizard_step'            => 'nullable|integer|min:1|max:5',
            'payload_patch'          => 'nullable|array',
            'ui_state_patch'         => 'nullable|array',
            'images_manifest_patch'  => 'nullable|array',
            'ai_state_patch'         => 'nullable|array',
            'client_saved_at'        => 'nullable|date',
        ]);

        $draft = $this->findDraftOrFail($user->id, $draftId);

        if ((int) $validated['version'] !== (int) $draft->version) {
            return response()->json([
                'message' => 'Draft version conflict.',
                'code' => 'version_conflict',
                'server' => $draft,
            ], 409);
        }

        if (array_key_exists('status', $validated)) {
            $draft->status = $validated['status'] ?? $draft->status;
        }
        if (array_key_exists('wizard_step', $validated)) {
            $draft->wizard_step = (int) $validated['wizard_step'];
        }
        if (array_key_exists('payload_patch', $validated)) {
            $draft->payload_json = $this->deepMerge($draft->payload_json ?? [], $validated['payload_patch'] ?? []);
        }
        if (array_key_exists('ui_state_patch', $validated)) {
            $draft->ui_state_json = $this->deepMerge($draft->ui_state_json ?? [], $validated['ui_state_patch'] ?? []);
        }
        if (array_key_exists('images_manifest_patch', $validated)) {
            $draft->images_manifest_json = $this->deepMerge($draft->images_manifest_json ?? [], $validated['images_manifest_patch'] ?? []);
        }
        if (array_key_exists('ai_state_patch', $validated)) {
            $draft->ai_state_json = $this->deepMerge($draft->ai_state_json ?? [], $validated['ai_state_patch'] ?? []);
        }
        if (array_key_exists('client_saved_at', $validated)) {
            $draft->last_client_saved_at = $validated['client_saved_at'];
        }

        $draft->version = (int) $draft->version + 1;
        $draft->save();

        return response()->json($draft);
    }

    public function attachYacht(Request $request, string $draftId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'yacht_id' => 'required|integer|exists:yachts,id',
            'version'  => 'nullable|integer|min:1',
        ]);

        $draft = $this->findDraftOrFail($user->id, $draftId);
        if (isset($validated['version']) && (int) $validated['version'] !== (int) $draft->version) {
            return response()->json([
                'message' => 'Draft version conflict.',
                'code' => 'version_conflict',
                'server' => $draft,
            ], 409);
        }

        $yacht = Yacht::findOrFail((int) $validated['yacht_id']);
        if (!$this->canAccessYacht($user, $yacht)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $draft->yacht_id = $yacht->id;
        $draft->version = (int) $draft->version + 1;
        $draft->save();

        return response()->json($draft);
    }

    public function commit(Request $request, string $draftId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'version' => 'nullable|integer|min:1',
        ]);

        $draft = $this->findDraftOrFail($user->id, $draftId);
        if (isset($validated['version']) && (int) $validated['version'] !== (int) $draft->version) {
            return response()->json([
                'message' => 'Draft version conflict.',
                'code' => 'version_conflict',
                'server' => $draft,
            ], 409);
        }

        $draft->status = 'submitted';
        $draft->version = (int) $draft->version + 1;
        $draft->save();

        return response()->json($draft);
    }

    private function findDraftOrFail(int $userId, string $draftId): YachtDraft
    {
        return YachtDraft::query()
            ->where('user_id', $userId)
            ->where('draft_id', $draftId)
            ->firstOrFail();
    }

    private function deepMerge(array $existing, array $patch): array
    {
        return array_replace_recursive($existing, $patch);
    }

    private function canAccessYacht($user, Yacht $yacht): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return (int) $yacht->user_id === (int) $user->id;
    }
}

