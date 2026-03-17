<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('boat_fields')->cascadeOnDelete();
            $table->string('source', 30);
            $table->string('external_key', 120)->nullable();
            $table->string('external_value', 191);
            $table->string('normalized_value', 191);
            $table->string('match_type', 20)->default('exact');
            $table->timestamps();

            $table->index(['field_id', 'source']);
            $table->index(['source', 'external_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_field_mappings');
    }
};
