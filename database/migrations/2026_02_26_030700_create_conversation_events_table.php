<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'type']);
            $table->index('created_at');

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_events');
    }
};
