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
        Schema::create('yachtshift_raw_boats', function (Blueprint $table) {
            $table->id();
            $table->string('yachtshift_id')->unique();
            $table->json('raw_payload');
            $table->enum('status', ['imported', 'normalized', 'failed'])->default('imported');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yachtshift_raw_boats');
    }
};
