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
        if (Schema::hasTable('social_logs')) {
            return;
        }

        Schema::create('social_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_post_id')->nullable()->constrained('video_posts')->onDelete('set null');
            $table->string('provider')->default('yext');
            $table->string('event')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->integer('status_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_logs');
    }
};
