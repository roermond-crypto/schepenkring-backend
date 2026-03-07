<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RiskLevel;
use App\Models\CopilotAction;
use App\Http\Controllers\Controller;
use App\Services\ActionSecurity;
use Illuminate\Http\Request;

class CopilotActionController extends Controller
{
    public function __construct(private ActionSecurity $security)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = CopilotAction::query();
        if ($request->filled('enabled')) {
            $query->where('enabled', (bool) $request->query('enabled'));
        }
        if ($request->filled('module')) {
            $query->where('module', $request->query('module'));
        }

        return response()->json($query->orderBy('module')->orderBy('title')->get());
    }

    public function show(Request $request, CopilotAction $action)
    {
        $this->authorizeAdmin($request);

        return response()->json($action);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'action_id' => 'required|string|max:120|unique:copilot_actions,action_id',
            'title' => 'required|string|max:160',
            'short_description' => 'nullable|string|max:255',
            'module' => 'nullable|string|max:80',
            'description' => 'nullable|string',
            'route_template' => 'required|string|max:255',
            'query_template' => 'nullable|string|max:255',
            'required_params' => 'nullable|array',
            'input_schema' => 'nullable|array',
            'example_inputs' => 'nullable|array',
            'example_prompts' => 'nullable|array',
            'side_effects' => 'nullable|array',
            'idempotency_rules' => 'nullable|array',
            'rate_limit_class' => 'nullable|string|max:40',
            'fresh_auth_required_minutes' => 'nullable|integer|min:1|max:1440',
            'tags' => 'nullable|array',
            'permission_key' => 'nullable|string|max:120',
            'required_role' => 'nullable|string|max:60',
            'risk_level' => 'nullable|string|in:low,medium,high',
            'confirmation_required' => 'nullable|boolean',
            'enabled' => 'nullable|boolean',
        ]);

        $action = CopilotAction::create(array_merge($validated, [
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        $this->logChange($request, 'created', $action, null, $action->toArray());

        return response()->json($action, 201);
    }

    public function update(Request $request, CopilotAction $action)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'title' => 'nullable|string|max:160',
            'short_description' => 'nullable|string|max:255',
            'module' => 'nullable|string|max:80',
            'description' => 'nullable|string',
            'route_template' => 'nullable|string|max:255',
            'query_template' => 'nullable|string|max:255',
            'required_params' => 'nullable|array',
            'input_schema' => 'nullable|array',
            'example_inputs' => 'nullable|array',
            'example_prompts' => 'nullable|array',
            'side_effects' => 'nullable|array',
            'idempotency_rules' => 'nullable|array',
            'rate_limit_class' => 'nullable|string|max:40',
            'fresh_auth_required_minutes' => 'nullable|integer|min:1|max:1440',
            'tags' => 'nullable|array',
            'permission_key' => 'nullable|string|max:120',
            'required_role' => 'nullable|string|max:60',
            'risk_level' => 'nullable|string|in:low,medium,high',
            'confirmation_required' => 'nullable|boolean',
            'enabled' => 'nullable|boolean',
        ]);

        $before = $action->toArray();
        $action->fill($validated);
        $action->updated_by = $request->user()?->id;
        $action->save();

        $this->logChange($request, 'updated', $action, $before, $action->toArray());

        return response()->json($action);
    }

    public function destroy(Request $request, CopilotAction $action)
    {
        $this->authorizeAdmin($request);

        $before = $action->toArray();
        $action->delete();

        $this->logChange($request, 'deleted', $action, $before, null);

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

    private function logChange(Request $request, string $action, CopilotAction $target, ?array $before, ?array $after): void
    {
        $this->security->log('copilot.action.' . $action, RiskLevel::MEDIUM, $request->user(), $target, [
            'module' => $after['module'] ?? $before['module'] ?? null,
        ], [
            'snapshot_before' => $before,
            'snapshot_after' => $after,
        ]);
    }
}
