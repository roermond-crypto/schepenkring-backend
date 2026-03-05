<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harbor_booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('harbor_id')->unique();
            $table->unsignedSmallInteger('default_duration_minutes')->default(60);
            $table->time('opening_hours_start')->default('09:00:00');
            $table->time('opening_hours_end')->default('17:00:00');
            $table->unsignedSmallInteger('slot_step_minutes')->default(15);
            $table->unsignedSmallInteger('max_boats_per_timeslot')->default(1);
            $table->unsignedSmallInteger('max_boats_per_day')->default(5);
            $table->unsignedSmallInteger('buffer_minutes')->default(15);
            $table->unsignedSmallInteger('min_booking_hours')->default(24);
            $table->unsignedSmallInteger('max_booking_days')->default(30);
            $table->boolean('count_pending')->default(true);
            $table->timestamps();

            $table->foreign('harbor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_booking_settings');
    }
};
