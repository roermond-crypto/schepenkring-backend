<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('video_type', ['horizontal', 'vertical', 'square'])->default('horizontal');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('settings_json');
            $table->json('ai_rules_json');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_templates');
    }
};
