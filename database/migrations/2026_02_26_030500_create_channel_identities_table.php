<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_identities', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->string('type'); // whatsapp|email|phone
            $table->string('external_thread_id')->nullable();
            $table->string('external_user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'external_thread_id']);
            $table->index('external_user_id');

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_identities');
    }
};
