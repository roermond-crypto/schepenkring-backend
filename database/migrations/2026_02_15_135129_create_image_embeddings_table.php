<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('image_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->unique();
            $table->string('public_url');
            $table->json('embedding'); // stores the 3072-dim vector as JSON
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('image_embeddings');
    }
};