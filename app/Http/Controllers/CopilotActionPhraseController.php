<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CopilotAction;
use App\Models\CopilotActionPhrase;
use Illuminate\Http\Request;

class CopilotActionPhraseController extends Controller
{
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

        $this->logChange($request, 'created', null, $phrase->toArray());

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

        $this->logChange($request, 'updated', $before, $phrase->toArray());

        return response()->json($phrase);
    }

    public function destroy(Request $request, CopilotActionPhrase $phrase)
    {
        $this->authorizeAdmin($request);

        $before = $phrase->toArray();
        $phrase->delete();

        $this->logChange($request, 'deleted', $before, null);

        return response()->json(['message' => 'deleted']);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthorized');
        }
        $role = strtolower((string) $user->role);
        if ($role !== 'admin' && $role !== 'superadmin') {
            abort(403, 'Forbidden');
        }
    }

    private function logChange(Request $request, string $action, ?array $before, ?array $after): void
    {
        $actionName = $after['copilot_action_id'] ?? $before['copilot_action_id'] ?? null;
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'entity_type' => 'copilot_action_phrase',
            'entity_id' => $after['id'] ?? $before['id'] ?? null,
            'entity_name' => $actionName ? 'Action ' . $actionName : null,
            'action' => $action,
            'description' => 'Copilot action phrase ' . $action,
            'severity' => 'info',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $before,
            'new_data' => $after,
            'metadata' => ['copilot_action_id' => $actionName],
        ]);
    }
}
