<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_field_priorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('boat_fields')->cascadeOnDelete();
            $table->string('boat_type_key', 80);
            $table->string('priority', 20);
            $table->timestamps();

            $table->unique(['field_id', 'boat_type_key']);
            $table->index(['boat_type_key', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_field_priorities');
    }
};
