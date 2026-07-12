<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type'); // 'lead' | 'user' | 'campaign_target' | 'call_session'
            $table->unsignedBigInteger('subject_id');
            // Matches the outcome -> action vocabulary from the Retell spec's
            // follow-up rules table: retry_call, use_callback_time,
            // send_ai_email, send_onboarding_link, create_urgent_followup,
            // create_appointment, open_bid_task, warm_transfer,
            // route_to_contract_support, stop, suppress, mark_invalid
            $table->string('next_action');
            $table->timestamp('due_at')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->string('last_outcome')->nullable();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suppression_reason')->nullable();
            $table->text('ai_summary')->nullable();
            $table->string('status')->default('open'); // open | done | cancelled
            $table->foreignId('related_yacht_id')->nullable()->constrained('yachts')->nullOnDelete();
            $table->foreignId('related_deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->uuid('related_chat_thread_id')->nullable(); // conversations.id
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
