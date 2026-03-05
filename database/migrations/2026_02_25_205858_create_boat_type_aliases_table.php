<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boat_type_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('raw_name')->unique();
            $table->foreignId('boat_type_id')->nullable()->constrained('boat_types')->nullOnDelete();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->unsignedInteger('evidence_count')->default(1);
            $table->boolean('is_reviewed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boat_type_aliases');
    }
};
