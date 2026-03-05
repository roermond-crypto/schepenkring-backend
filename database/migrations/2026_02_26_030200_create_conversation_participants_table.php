<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('agent');
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
