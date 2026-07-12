<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separate from audit_logs deliberately: audit_logs is the
        // compliance/security trail (risk_level, snapshots, actor) and keeps
        // that purpose unchanged. This is the UI-facing per-entity feed
        // (user/seller/buyer/yacht/location/deal/campaign timelines, Sales
        // Command Center) — voice/campaign/follow-up events write to both
        // where relevant, each for its own reason.
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type'); // 'lead' | 'user' | 'yacht' | 'location' | 'deal' | 'campaign' | 'call_session'
            $table->unsignedBigInteger('subject_id');
            $table->string('type'); // e.g. 'call.outbound.completed', 'campaign.email.sent', 'followup.created'
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
