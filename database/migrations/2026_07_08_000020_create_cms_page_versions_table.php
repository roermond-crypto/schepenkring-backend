<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->unsignedBigInteger('version');
            // Full snapshot of the page + its sections at save time (mirrors
            // EmailTemplateVersion's shape) — not a per-field diff log, a
            // point-in-time restore target.
            $table->json('snapshot');
            $table->string('change_note')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cms_page_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_versions');
    }
};
