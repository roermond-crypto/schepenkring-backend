<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaction_event_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('interaction_event_categories')->nullOnDelete();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('default_channels')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_event_types');
    }
};
