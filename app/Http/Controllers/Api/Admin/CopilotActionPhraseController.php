<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RiskLevel;
use App\Models\CopilotActionPhrase;
use App\Http\Controllers\Controller;
use App\Services\ActionSecurity;
use Illuminate\Http\Request;

class CopilotActionPhraseController extends Controller
{
    public function __construct(private ActionSecurity $security)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = CopilotActionPhrase::query()->with('action');
        if ($request->filled('action_id')) {
            $query->where('copilot_action_id', $request->query('action_id'));
        }
        if ($request->filled('language')) {
            $query->where('language', $request->query('language'));
        }
        if ($request->filled('enabled')) {
            $query->where('enabled', (bool) $request->query('enabled'));
        }

        return response()->json($query->orderByDesc('priority')->get());
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'copilot_action_id' => 'required|integer|exists:copilot_actions,id',
            'phrase' => 'required|string|max:160',
            'language' => 'nullable|string|max:5',
            'priority' => 'nullable|integer|min:0|max:100',
            'enabled' => 'nullable|boolean',
        ]);

        $phrase = CopilotActionPhrase::create(array_merge($validated, [
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        $this->logChange($request, 'created', $phrase, null, $phrase->toArray());

        return response()->json($phrase, 201);
    }

    public function update(Request $request, CopilotActionPhrase $phrase)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'phrase' => 'nullable|string|max:160',
            'language' => 'nullable|string|max:5',
            'priority' => 'nullable|integer|min:0|max:100',
            'enabled' => 'nullable|boolean',
        ]);

        $before = $phrase->toArray();
        $phrase->fill($validated);
        $phrase->updated_by = $request->user()?->id;
        $phrase->save();

        $this->logChange($request, 'updated', $phrase, $before, $phrase->toArray());

        return response()->json($phrase);
    }

    public function destroy(Request $request, CopilotActionPhrase $phrase)
    {
        $this->authorizeAdmin($request);

        $before = $phrase->toArray();
        $phrase->delete();

        $this->logChange($request, 'deleted', $phrase, $before, null);

        return response()->json(['message' => 'deleted']);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthorized');
        }
        if (! $user->isAdmin()) {
            abort(403, 'Forbidden');
        }
    }

    private function logChange(Request $request, string $action, CopilotActionPhrase $phrase, ?array $before, ?array $after): void
    {
        $actionName = $after['copilot_action_id'] ?? $before['copilot_action_id'] ?? null;
        $this->security->log('copilot.phrase.' . $action, RiskLevel::MEDIUM, $request->user(), $phrase, [
            'copilot_action_id' => $actionName,
        ], [
            'snapshot_before' => $before,
            'snapshot_after' => $after,
        ]);
    }
}
