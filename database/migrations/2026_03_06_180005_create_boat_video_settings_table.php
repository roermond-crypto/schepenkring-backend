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
        Schema::create('boat_video_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained('yachts')->onDelete('cascade');
            $table->boolean('auto_publish_social')->default(false);
            $table->text('caption_template')->nullable();
            $table->text('hashtags_template')->nullable();
            $table->json('platforms')->nullable();
            $table->string('video_crop_format')->default('16:9');
            $table->boolean('auto_generate_caption')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boat_video_settings');
    }
};
