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
        Schema::create('boat_catalog', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('models')->nullOnDelete();
            $table->foreignId('boat_type_id')->nullable()->constrained('boat_types')->nullOnDelete();
            $table->foreignId('engine_brand_id')->nullable()->constrained('engine_brands')->nullOnDelete();
            
            $table->integer('year')->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->json('image_urls')->nullable();
            
            $table->unsignedTinyInteger('quality_score')->default(0); // 0-100
            $table->enum('pinecone_status', ['pending', 'indexed', 'failed'])->default('pending');
            $table->foreignId('raw_boat_id')->nullable()->constrained('yachtshift_raw_boats')->nullOnDelete();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boat_catalog');
    }
};
