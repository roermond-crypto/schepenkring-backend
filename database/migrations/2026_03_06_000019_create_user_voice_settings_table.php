<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_voice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tts_voice_id')->nullable();
            $table->boolean('tts_enabled')->default(false);
            $table->string('stt_language', 10)->nullable();
            $table->decimal('speaking_rate', 4, 2)->nullable();
            $table->decimal('pitch', 4, 2)->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_voice_settings');
    }
};
