<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boat_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained('yachts')->onDelete('cascade');
            $table->string('video_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration')->nullable();
            $table->string('format')->nullable();
            $table->enum('status', ['draft', 'processed', 'published'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boat_videos');
    }
};
