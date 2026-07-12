<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            // Polymorphic — e.g. usable_type='cms_section', usable_id=42,
            // field_key='background_image'. Lets the Media Library answer
            // "where is this image used" without scanning every content table.
            $table->string('usable_type');
            $table->unsignedBigInteger('usable_id');
            $table->string('field_key')->nullable();
            $table->timestamps();

            $table->index(['usable_type', 'usable_id']);
            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
    }
};
