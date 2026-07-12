<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // One of the 14 focused agents from spec §8, e.g.
            // seller_outbound_nl, buyer_support, schepenkring_reception —
            // deliberately separate agents/prompts per purpose+language
            // rather than one oversized prompt, per the spec's own §8.
            $table->string('slug')->unique();
            $table->string('language', 5)->nullable();
            $table->text('purpose')->nullable();
            $table->string('retell_agent_id')->nullable();
            $table->string('voice')->nullable();
            $table->string('model')->nullable();
            $table->longText('prompt')->nullable();
            $table->json('calling_hours')->nullable();
            $table->json('retry_rules')->nullable();
            $table->decimal('spend_cap_eur', 10, 2)->nullable();
            $table->string('status')->default('inactive'); // active | inactive
            $table->json('knowledge_categories')->nullable(); // Faq.category values this agent may ground answers in
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_agents');
    }
};
