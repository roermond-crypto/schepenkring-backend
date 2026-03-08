<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('video_posts')) {
            return;
        }

        Schema::create('video_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained('videos')->onDelete('cascade');
            $table->string('yext_post_id')->nullable();
            $table->json('publishers')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->enum('status', ['scheduled', 'publishing', 'published', 'failed'])->default('scheduled');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('engagement')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('yext_account_id')->nullable();
            $table->string('yext_entity_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_posts');
    }
};
