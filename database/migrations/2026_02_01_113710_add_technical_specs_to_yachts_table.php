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
        Schema::table('yachts', function (Blueprint $table) {
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('beam')->nullable(); // Width
            $table->string('draft')->nullable(); // Depth in water
            $table->string('engine_type')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('fuel_capacity')->nullable();
            $table->string('water_capacity')->nullable();
            $table->integer('cabins')->default(0);
            $table->integer('heads')->default(0); // Bathrooms
            $table->text('description')->nullable();
            $table->string('location')->nullable();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            //
        });
    }
};
