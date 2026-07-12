<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
            // Matches a key in App\Services\Cms\CmsComponentRegistry — e.g.
            // 'HeroSection', 'FeatureGrid'. Validated against the registry on
            // save, not a free string.
            $table->string('component');
            $table->string('variant')->nullable();
            // {field_key: {nl:"...",en:"...",de:"...",fr:"..."}} for translatable
            // fields, or a plain scalar/array for non-translatable ones (media
            // ids, urls, numbers) — which fields are which is defined by the
            // component's registry entry, not inferred from the JSON shape.
            $table->json('content')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['cms_page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_sections');
    }
};
