<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copilot_audit_events', function (Blueprint $table) {
            $table->string('stage', 20)->nullable()->after('source');
            $table->json('matching_detail')->nullable()->after('resolved_action_candidates');
            $table->json('validation_result')->nullable()->after('matching_detail');
            $table->json('execution_result')->nullable()->after('validation_result');
            $table->unsignedInteger('duration_ms')->nullable()->after('execution_result');
            $table->string('user_correction_action_id')->nullable()->after('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('copilot_audit_events', function (Blueprint $table) {
            $table->dropColumn([
                'stage',
                'matching_detail',
                'validation_result',
                'execution_result',
                'duration_ms',
                'user_correction_action_id',
            ]);
        });
    }
};
