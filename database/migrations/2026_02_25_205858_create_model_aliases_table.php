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
        Schema::create('model_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('raw_name');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('models')->nullOnDelete();
            $table->unsignedTinyInteger('confidence')->default(0); 
            $table->unsignedInteger('evidence_count')->default(1);
            $table->boolean('is_reviewed')->default(false);
            $table->timestamps();
            
            $table->unique(['brand_id', 'raw_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_aliases');
    }
};
