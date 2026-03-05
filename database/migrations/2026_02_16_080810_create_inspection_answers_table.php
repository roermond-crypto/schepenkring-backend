<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inspection_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('boat_inspections')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('boat_check')->onDelete('cascade');
            $table->text('ai_answer')->nullable();
            $table->float('ai_confidence')->nullable();
            $table->text('human_answer')->nullable();
            $table->enum('review_status', ['accepted', 'overridden', 'verified'])->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('inspection_answers');
    }
};