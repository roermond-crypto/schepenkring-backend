<?php

namespace App\Services;

use App\Models\CopilotAction;
use App\Models\CopilotActionPhrase;
use Illuminate\Support\Str;

class CopilotActionCatalogService
{
    public function buildCatalog(): array
    {
        $actions = CopilotAction::query()
            ->where('enabled', true)
            ->orderBy('module')
            ->orderBy('title')
            ->get();

        $phrases = CopilotActionPhrase::query()
            ->where('enabled', true)
            ->get()
            ->groupBy('copilot_action_id');

        $items = [];

        foreach ($actions as $action) {
            $actionPhrases = $phrases->get($action->id, collect())
                ->pluck('phrase')
                ->filter()
                ->values()
                ->all();

            $items[] = [
                'action_id' => $action->action_id,
                'title' => $action->title,
                'short_description' => $action->short_description
                    ?: ($action->description ? Str::limit($action->description, 140) : null),
                'description' => $action->description,
                'module' => $action->module,
                'required_role' => $action->required_role,
                'permission_key' => $action->permission_key,
                'security_level' => $action->risk_level,
                'input_schema' => $action->input_schema,
                'example_inputs' => $action->example_inputs ?? [],
                'example_prompts' => $action->example_prompts ?? [],
                'side_effects' => $action->side_effects ?? [],
                'idempotency_rules' => $action->idempotency_rules ?? [],
                'rate_limit_class' => $action->rate_limit_class,
                'fresh_auth_required_minutes' => $action->fresh_auth_required_minutes,
                'confirmation_required' => (bool) $action->confirmation_required,
                'route_template' => $action->route_template,
                'query_template' => $action->query_template,
                'required_params' => $action->required_params ?? [],
                'tags' => $action->tags ?? [],
                'phrases' => $actionPhrases,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'count' => count($items),
            'actions' => $items,
        ];
    }
}
