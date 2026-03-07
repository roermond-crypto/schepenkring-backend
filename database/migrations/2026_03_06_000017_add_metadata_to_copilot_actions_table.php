<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copilot_actions', function (Blueprint $table) {
            $table->string('short_description')->nullable()->after('title');
            $table->string('required_role')->nullable()->after('permission_key');
            $table->json('input_schema')->nullable()->after('required_params');
            $table->json('example_inputs')->nullable()->after('input_schema');
            $table->json('example_prompts')->nullable()->after('example_inputs');
            $table->json('side_effects')->nullable()->after('example_prompts');
            $table->json('idempotency_rules')->nullable()->after('side_effects');
            $table->string('rate_limit_class')->nullable()->after('idempotency_rules');
            $table->unsignedInteger('fresh_auth_required_minutes')->nullable()->after('rate_limit_class');
            $table->json('tags')->nullable()->after('fresh_auth_required_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('copilot_actions', function (Blueprint $table) {
            $table->dropColumn([
                'short_description',
                'required_role',
                'input_schema',
                'example_inputs',
                'example_prompts',
                'side_effects',
                'idempotency_rules',
                'rate_limit_class',
                'fresh_auth_required_minutes',
                'tags',
            ]);
        });
    }
};
