<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // e.g. 'boot-aanmelden', 'homepage'
            $table->string('name'); // internal label, not shown to visitors
            // draft | review | published | scheduled | archived
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            // {title:{nl,en,de,fr}, description:{...}, og_title:{...},
            //  og_description:{...}, og_image_media_id, canonical_url, robots,
            //  structured_data_type} — same shape used per-language elsewhere
            // in this app (BoatField.labels_json, EmailTemplate.subject).
            $table->json('seo')->nullable();
            $table->unsignedBigInteger('current_version')->default(1);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
