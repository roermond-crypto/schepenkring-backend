<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('draft_id', 120);
            $table->foreignId('yacht_id')->nullable()->constrained('yachts')->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->unsignedTinyInteger('wizard_step')->default(1);
            $table->json('payload_json')->nullable();
            $table->json('ui_state_json')->nullable();
            $table->json('images_manifest_json')->nullable();
            $table->json('ai_state_json')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_client_saved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'draft_id']);
            $table->index(['user_id', 'status', 'updated_at']);
            $table->index(['yacht_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_drafts');
    }
};

