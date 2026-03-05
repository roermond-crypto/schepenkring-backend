<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    // Rules for when a yacht is generally available (e.g., Mon-Sat 10:00-18:00)
    Schema::create('yacht_availability_rules', function (Blueprint $table) {
        $table->id();
        $table->foreignId('yacht_id')->constrained()->onDelete('cascade');
        $table->integer('day_of_week'); // 0 (Sun) to 6 (Sat)
        $table->time('start_time');
        $table->time('end_time');
        $table->timestamps();
    });

    // The actual bookings/holds to check for overlaps
    Schema::create('bookings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('yacht_id')->constrained();
        $table->dateTime('start_at');
        $table->dateTime('end_at'); // This will store start + 60m + 15m buffer
        $table->string('status')->default('confirmed');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yacht_scheduling_tables');
    }
};
