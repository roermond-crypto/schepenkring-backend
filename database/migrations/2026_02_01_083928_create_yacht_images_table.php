<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('yacht_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained()->onDelete('cascade');
            $table->string('url'); // Path to image
            $table->string('category')->nullable(); // e.g. 'Engine', 'Interior', 'Deck'
            $table->string('part_name')->nullable(); // e.g. 'Turbocharger Left'
            $table->integer('sort_order')->default(0); 
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('yacht_images');
    }
};