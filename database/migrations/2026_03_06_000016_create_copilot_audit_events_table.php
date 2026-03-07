<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 20)->index();
            $table->text('input_text')->nullable();
            $table->json('resolved_action_candidates')->nullable();
            $table->string('selected_action_id')->nullable();
            $table->json('selected_action_params')->nullable();
            $table->string('deeplink_returned')->nullable();
            $table->decimal('confidence', 5, 3)->nullable();
            $table->string('status', 30)->default('resolved');
            $table->string('failure_reason')->nullable();
            $table->string('request_id', 80)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_audit_events');
    }
};
