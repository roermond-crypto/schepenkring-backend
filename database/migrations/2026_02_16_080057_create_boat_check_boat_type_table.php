<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('boat_check_boat_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boat_check_id')->constrained('boat_check')->onDelete('cascade');
            $table->foreignId('boat_type_id')->constrained('boat_types')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('boat_check_boat_type');
    }
};