<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_send_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->nullable();
            $table->uuid('message_id')->nullable();
            $table->unsignedBigInteger('harbor_id')->nullable();
            $table->string('template_name');
            $table->string('language', 10)->nullable();
            $table->json('params')->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->index(['harbor_id', 'template_name']);
            $table->index('conversation_id');
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_send_logs');
    }
};
