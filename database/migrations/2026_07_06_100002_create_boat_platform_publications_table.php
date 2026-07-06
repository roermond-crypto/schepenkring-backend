<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_platform_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained('yachts')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('external_platform_id')->nullable();
            // pending | published | synced | failed | paused
            $table->string('status')->default('pending');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->string('last_payload_hash')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->unique(['yacht_id', 'platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_platform_publications');
    }
};
