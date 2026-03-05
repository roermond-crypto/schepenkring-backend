<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('yacht_id')->constrained('yachts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'yacht_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_likes');
    }
};
