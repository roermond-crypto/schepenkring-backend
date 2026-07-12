<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_values', function (Blueprint $table) {
            $table->id();
            // Matches boat_fields.internal_key — the governed field this value
            // belongs to (e.g. 'manufacturer', 'model', 'boat_type').
            $table->string('field_key', 120);
            $table->string('value', 255);
            // Lowercased, whitespace/punctuation-collapsed form of `value`, used
            // for exact-duplicate prevention and as the base for fuzzy scoring.
            $table->string('normalized_value', 255);
            $table->unsignedInteger('usage_count')->default(0);
            $table->string('status', 20)->default('active'); // active | archived
            $table->foreignId('merged_into_id')->nullable()->constrained('catalog_values')->nullOnDelete();
            // For hierarchical fields (e.g. model belongs to a brand value).
            $table->foreignId('parent_value_id')->nullable()->constrained('catalog_values')->nullOnDelete();
            $table->string('created_via', 20)->default('manual'); // manual | ai_suggested | import | seed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['field_key', 'normalized_value']);
            $table->index(['field_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_values');
    }
};
