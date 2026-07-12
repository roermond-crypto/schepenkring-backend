<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // seller_followup | buyer_followup | harbor_outreach |
            // viewing_confirmation | bid_followup | contract_reminder |
            // payment_reminder | callback_followup | onboarding_incomplete
            $table->string('type');
            $table->string('status')->default('draft'); // draft | active | paused | completed
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->json('target_criteria')->nullable();
            // Matches EmailTemplate.type — no enum constraint, same
            // free-form convention EmailTemplateResolver already uses.
            $table->string('email_template_key')->nullable();
            $table->string('voice_agent_id')->nullable();
            $table->json('calling_hours')->nullable();
            $table->unsignedTinyInteger('max_call_attempts')->default(3);
            $table->unsignedInteger('retry_delay_hours')->default(24);
            $table->decimal('spend_cap_eur', 10, 2)->nullable();
            $table->decimal('spend_to_date_eur', 10, 2)->default(0);
            $table->unsignedInteger('min_score_to_call')->default(20);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'type']);
        });

        Schema::create('campaign_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            // 'lead' | 'user' | 'contact' — polymorphic without Eloquent's
            // morph helpers, since each target type is looked up differently
            // (Lead has direct email/phone; User needs sellerProfile/
            // buyerProfile; Contact is the Chat Hub's own identity record).
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('status')->default('pending');
            // pending -> emailed -> scored -> queued_for_call -> called -> completed
            // (or suppressed / failed at any point)
            $table->unsignedInteger('score')->default(0);
            $table->unsignedTinyInteger('call_attempts')->default(0);
            $table->timestamp('last_action_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->string('suppression_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'target_type', 'target_id']);
            $table->index(['status', 'next_action_at']);
        });

        Schema::table('call_sessions', function (Blueprint $table) {
            $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
        });

        Schema::dropIfExists('campaign_targets');
        Schema::dropIfExists('campaigns');
    }
};
