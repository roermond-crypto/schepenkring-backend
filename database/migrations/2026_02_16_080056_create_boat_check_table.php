<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('boat_check', function (Blueprint $table) {
            $table->id();
            $table->text('question_text');
            $table->enum('type', ['YES_NO', 'MULTI', 'TEXT', 'DATE']);
            $table->boolean('required')->default(false);
            $table->text('ai_prompt')->nullable();
            $table->json('evidence_sources')->nullable();
            $table->enum('weight', ['low', 'medium', 'high'])->default('medium');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('boat_check');
    }
};