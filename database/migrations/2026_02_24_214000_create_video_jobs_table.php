<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained('yachts')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['queued', 'processing', 'done', 'failed'])->default('queued');
            $table->string('video_path')->nullable();
            $table->text('error_log')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->string('music_track')->nullable();
            $table->boolean('has_voiceover')->default(false);
            $table->string('voiceover_path')->nullable();
            $table->integer('image_count')->default(0);
            $table->integer('progress')->default(0); // 0-100
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_jobs');
    }
};
