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
        Schema::create('engine_brand_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('raw_name')->unique();
            $table->foreignId('engine_brand_id')->nullable()->constrained('engine_brands')->nullOnDelete();
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
        Schema::dropIfExists('engine_brand_aliases');
    }
};
