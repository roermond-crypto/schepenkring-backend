<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('copilot_actions')->updateOrInsert(
            ['action_id' => 'create.boat'],
            [
                'title' => 'Create boat',
                'short_description' => 'Open the admin flow for creating a new boat.',
                'module' => 'yachts',
                'description' => 'Use when an admin asks copilot to create, add, or register a boat/yacht.',
                'route_template' => '/admin/yachts/new',
                'query_template' => null,
                'required_params' => json_encode([]),
                'input_schema' => json_encode([
                    'type' => 'object',
                    'properties' => new stdClass(),
                ]),
                'example_inputs' => json_encode([
                    'create boat',
                    'add new yacht',
                    'nieuwe boot aanmaken',
                ]),
                'example_prompts' => json_encode([
                    'Create a boat',
                    'Open the new yacht form',
                ]),
                'side_effects' => json_encode([
                    'Opens the admin new-yacht form. It does not create a database record until the form is submitted.',
                ]),
                'idempotency_rules' => json_encode([
                    'Repeated execution opens the same blank create form.',
                ]),
                'rate_limit_class' => 'navigation',
                'fresh_auth_required_minutes' => null,
                'tags' => json_encode(['boat', 'yacht', 'create', 'admin']),
                'permission_key' => null,
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $actionId = DB::table('copilot_actions')
            ->where('action_id', 'create.boat')
            ->value('id');

        if (! $actionId) {
            return;
        }

        foreach ($this->phrases() as $phrase) {
            DB::table('copilot_action_phrases')->updateOrInsert(
                [
                    'copilot_action_id' => $actionId,
                    'phrase' => $phrase['phrase'],
                    'language' => $phrase['language'],
                ],
                [
                    'priority' => $phrase['priority'],
                    'enabled' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $actionId = DB::table('copilot_actions')
            ->where('action_id', 'create.boat')
            ->value('id');

        if ($actionId) {
            DB::table('copilot_action_phrases')
                ->where('copilot_action_id', $actionId)
                ->delete();
        }

        DB::table('copilot_actions')
            ->where('action_id', 'create.boat')
            ->delete();
    }

    private function phrases(): array
    {
        return [
            ['phrase' => 'create boat', 'language' => 'en', 'priority' => 100],
            ['phrase' => 'add boat', 'language' => 'en', 'priority' => 95],
            ['phrase' => 'new boat', 'language' => 'en', 'priority' => 90],
            ['phrase' => 'create yacht', 'language' => 'en', 'priority' => 95],
            ['phrase' => 'add new yacht', 'language' => 'en', 'priority' => 90],
            ['phrase' => 'open new yacht form', 'language' => 'en', 'priority' => 85],
            ['phrase' => 'boot aanmaken', 'language' => 'nl', 'priority' => 100],
            ['phrase' => 'nieuwe boot', 'language' => 'nl', 'priority' => 95],
            ['phrase' => 'jacht aanmaken', 'language' => 'nl', 'priority' => 95],
            ['phrase' => 'nieuwe yacht', 'language' => 'nl', 'priority' => 80],
        ];
    }
};
