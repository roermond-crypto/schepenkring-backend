<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk_path'); // optimized/master file, storage-relative
            $table->string('thumb_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('alt_text')->nullable();  // {nl,en,de,fr}
            $table->json('caption')->nullable();   // {nl,en,de,fr}
            $table->json('seo_title')->nullable(); // {nl,en,de,fr}
            // 0.0-1.0 relative position, used for object-position / smart crops.
            $table->decimal('focal_point_x', 4, 3)->nullable();
            $table->decimal('focal_point_y', 4, 3)->nullable();
            $table->json('crop_data')->nullable();
            // uploading | processing | optimized | needs_review
            $table->string('status')->default('uploading');
            $table->boolean('ai_alt_text_is_draft')->default(false);
            $table->boolean('ai_seo_title_is_draft')->default(false);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
