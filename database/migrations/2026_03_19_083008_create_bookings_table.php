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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boat_id')->nullable()->constrained('yachts')->nullOnDelete();
            $table->string('type')->default('viewing');
            $table->string('status')->default('pending');
            $table->date('date');
            $table->time('time');
            $table->integer('duration_minutes')->default(60);
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
