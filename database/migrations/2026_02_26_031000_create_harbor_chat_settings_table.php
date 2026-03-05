<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harbor_chat_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('harbor_id')->unique();
            $table->boolean('ai_enabled')->default(true);
            $table->string('ai_mode_default')->default('auto');
            $table->time('business_hours_start')->nullable();
            $table->time('business_hours_end')->nullable();
            $table->string('timezone')->nullable();
            $table->unsignedInteger('first_response_minutes')->default(30);
            $table->unsignedInteger('escalation_minutes')->default(60);
            $table->text('offline_message')->nullable();
            $table->timestamps();

            $table->foreign('harbor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_chat_settings');
    }
};
