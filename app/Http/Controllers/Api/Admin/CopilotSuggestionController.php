<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Models\CopilotActionSuggestion;
use App\Services\ActionSecurity;
use App\Services\CopilotLearningService;
use Illuminate\Http\Request;

class CopilotSuggestionController extends Controller
{
    public function __construct(
        private CopilotLearningService $learning,
        private ActionSecurity $security
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = CopilotActionSuggestion::query()
            ->with(['targetAction', 'createdAction', 'reviewer'])
            ->orderByDesc('confidence')
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('suggestion_type')) {
            $query->where('suggestion_type', $request->query('suggestion_type'));
        }
        if ($request->filled('module')) {
            $query->where('module', $request->query('module'));
        }

        return response()->json($query->paginate((int) $request->query('per_page', 25)));
    }

    public function show(Request $request, CopilotActionSuggestion $suggestion)
    {
        $this->authorizeAdmin($request);

        return response()->json($suggestion->load(['targetAction', 'createdAction', 'reviewer']));
    }

    public function mine(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'auto_create' => 'nullable|boolean',
        ]);

        $result = $this->learning->mineFromHistory(
            $validated['days'] ?? null,
            (bool) ($validated['auto_create'] ?? false)
        );

        return response()->json([
            'count' => $result['count'],
            'data' => $result['data']->values(),
        ]);
    }

    public function update(Request $request, CopilotActionSuggestion $suggestion)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:160',
            'short_description' => 'sometimes|nullable|string|max:255',
            'module' => 'sometimes|nullable|string|max:80',
            'description' => 'sometimes|nullable|string',
            'route_template' => 'sometimes|nullable|string|max:255',
            'query_template' => 'sometimes|nullable|string|max:255',
            'required_params' => 'sometimes|nullable|array',
            'input_schema' => 'sometimes|nullable|array',
            'phrases' => 'sometimes|nullable|array',
            'phrases.*.phrase' => 'required_with:phrases|string|max:160',
            'phrases.*.language' => 'nullable|string|max:5',
            'phrases.*.priority' => 'nullable|integer|min:0|max:100',
            'permission_key' => 'sometimes|nullable|string|max:120',
            'required_role' => 'sometimes|nullable|string|max:60',
            'risk_level' => 'sometimes|nullable|string|in:low,medium,high',
            'confirmation_required' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:pending,disabled,rejected',
            'reasoning' => 'sometimes|nullable|string',
        ]);

        $before = $suggestion->toArray();
        $suggestion->fill($validated);
        if (isset($validated['status']) && in_array($validated['status'], ['disabled', 'rejected'], true)) {
            $suggestion->reviewed_by = $request->user()?->id;
            $suggestion->reviewed_at = now();
        }
        $suggestion->save();
        $this->learning->syncSuggestion($suggestion);

        $this->security->log('copilot.suggestion.updated', RiskLevel::MEDIUM, $request->user(), $suggestion, [
            'suggestion_type' => $suggestion->suggestion_type,
        ], [
            'entity_type' => CopilotActionSuggestion::class,
            'entity_id' => $suggestion->id,
            'snapshot_before' => $before,
            'snapshot_after' => $suggestion->toArray(),
        ]);

        return response()->json($suggestion->fresh(['targetAction', 'createdAction', 'reviewer']));
    }

    public function approve(Request $request, CopilotActionSuggestion $suggestion)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'action_id' => 'sometimes|nullable|string|max:120',
            'title' => 'sometimes|nullable|string|max:160',
            'short_description' => 'sometimes|nullable|string|max:255',
            'module' => 'sometimes|nullable|string|max:80',
            'description' => 'sometimes|nullable|string',
            'route_template' => 'sometimes|nullable|string|max:255',
            'query_template' => 'sometimes|nullable|string|max:255',
            'required_params' => 'sometimes|nullable|array',
            'input_schema' => 'sometimes|nullable|array',
            'phrases' => 'sometimes|nullable|array',
            'phrases.*.phrase' => 'required_with:phrases|string|max:160',
            'phrases.*.language' => 'nullable|string|max:5',
            'phrases.*.priority' => 'nullable|integer|min:0|max:100',
            'permission_key' => 'sometimes|nullable|string|max:120',
            'required_role' => 'sometimes|nullable|string|max:60',
            'risk_level' => 'sometimes|nullable|string|in:low,medium,high',
            'confirmation_required' => 'sometimes|boolean',
        ]);

        $before = $suggestion->toArray();
        $approved = $this->learning->approveSuggestion($suggestion, $request->user(), $validated);

        $this->security->log('copilot.suggestion.approved', RiskLevel::MEDIUM, $request->user(), $approved, [
            'created_action_id' => $approved->created_action_id,
            'suggestion_type' => $approved->suggestion_type,
        ], [
            'entity_type' => CopilotActionSuggestion::class,
            'entity_id' => $approved->id,
            'snapshot_before' => $before,
            'snapshot_after' => $approved->fresh()->toArray(),
        ]);

        return response()->json($approved->fresh(['targetAction', 'createdAction', 'reviewer']));
    }

    public function disable(Request $request, CopilotActionSuggestion $suggestion)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'status' => 'nullable|string|in:disabled,rejected',
        ]);

        $before = $suggestion->toArray();
        $updated = $this->learning->disableSuggestion(
            $suggestion,
            $request->user(),
            $validated['status'] ?? 'disabled'
        );

        $this->security->log('copilot.suggestion.disabled', RiskLevel::LOW, $request->user(), $updated, [
            'status' => $updated->status,
        ], [
            'entity_type' => CopilotActionSuggestion::class,
            'entity_id' => $updated->id,
            'snapshot_before' => $before,
            'snapshot_after' => $updated->fresh()->toArray(),
        ]);

        return response()->json($updated->fresh(['targetAction', 'createdAction', 'reviewer']));
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthorized');
        }
        if (! $user->isAdmin()) {
            abort(403, 'Forbidden');
        }
    }
}
