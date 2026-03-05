<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('harbor_widget_ai_advice', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('harbor_id');
            $table->date('week_start');
            $table->json('issues')->nullable();
            $table->json('suggestions')->nullable();
            $table->string('priority')->nullable();
            $table->text('user_message')->nullable();
            $table->timestamps();

            $table->unique(['harbor_id', 'week_start']);
            $table->index(['harbor_id', 'week_start']);
            $table->foreign('harbor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_widget_ai_advice');
    }
};
