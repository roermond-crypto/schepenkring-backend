<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('yachts', function (Blueprint $table) {
            $table->id();
            $table->string('vessel_id')->unique();
            $table->string('name');
            $table->enum('status', ['For Sale', 'For Bid', 'Sold', 'Draft'])->default('Draft');
            $table->decimal('price', 15, 2);
            $table->decimal('current_bid', 15, 2)->nullable();
            $table->string('year');
            $table->string('length');
            $table->string('main_image')->nullable(); // Thumbnail for the main list
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('yachts');
    }
};